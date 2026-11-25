<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Services\OutgoingMail;
use App\Services\ShopAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminMessageController extends Controller
{
    public function index(Request $request)
    {
        $shop = ShopAccess::authorize($request, 'messages.manage');
        $input = $request->validate([
            'status' => ['nullable', Rule::in(['new', 'read', 'resolved'])],
            'search' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $query = ContactMessage::where('shop_id', $shop->id)->with('latestReply');
        if (! empty($input['status'])) {
            $query->where('status', $input['status']);
        }
        if (! empty($input['search'])) {
            $query->where(function ($query) use ($input) {
                $search = '%'.$input['search'].'%';
                $query->where('name', 'like', $search)->orWhere('email', 'like', $search)->orWhere('message', 'like', $search);
            });
        }

        return response()->json($query->orderByDesc('id')->paginate(10))->header('Cache-Control', 'no-store');
    }

    public function reply(Request $request, int $message)
    {
        $shop = ShopAccess::authorize($request, 'messages.manage');
        $input = $request->validate([
            'body' => 'required|string|max:3000',
            'request_id' => 'required|uuid',
        ]);

        return DB::transaction(function () use ($shop, $message, $request, $input) {
            $enquiry = ContactMessage::where('shop_id', $shop->id)->whereKey($message)->lockForUpdate()->firstOrFail();
            $existing = $enquiry->replies()->where('request_id', $input['request_id'])->first();
            if ($existing) {
                abort_unless($existing->body === $input['body'], 409, 'This reply was already recorded. Refresh the inbox before sending an edited message.');

                return response()->json(['message' => 'This reply was already recorded.', 'reply' => $existing])
                    ->header('Cache-Control', 'no-store');
            }
            abort_if($enquiry->replies()->where('status', 'pending')->exists(), 409, 'A reply is already queued for this enquiry.');
            abort_unless(OutgoingMail::ready(), 503, 'Reply delivery is unavailable. Check the mail configuration and try again.');

            $reply = $enquiry->replies()->create($input + ['status' => 'pending']);
            ShopAccess::audit($request, 'message.reply_queued', $enquiry->id);

            return response()->json(['message' => 'Reply queued for delivery.', 'reply' => $reply], 202)
                ->header('Cache-Control', 'no-store');
        });
    }

    public function update(Request $request, int $message)
    {
        $shop = ShopAccess::authorize($request, 'messages.manage');
        $current = ContactMessage::where('shop_id', $shop->id)->findOrFail($message);
        $input = $request->validate([
            'status' => ['required', Rule::in(['new', 'read', 'resolved'])],
            'expected_status' => ['required', Rule::in(['new', 'read', 'resolved'])],
        ]);

        return DB::transaction(function () use ($request, $current, $input, $shop) {
            $message = ContactMessage::where('shop_id', $shop->id)->whereKey($current->id)->lockForUpdate()->firstOrFail();
            if ($message->status !== $input['expected_status']) {
                return response()->json(['message' => 'This enquiry changed. Refresh the inbox before updating it.'], 409);
            }
            $message->update(['status' => $input['status']]);
            ShopAccess::audit($request, 'message.status_updated', $message->id);

            return response()->json(['message' => 'Enquiry status updated.', 'enquiry' => $message])->header('Cache-Control', 'no-store');
        });
    }
}
