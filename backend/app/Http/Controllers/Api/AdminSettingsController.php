<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ShopAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminSettingsController extends Controller
{
    private function authorizeAdmin(Request $request): void
    {
        ShopAccess::authorize($request, 'bookings.read');
    }

    public function dashboard(Request $request)
    {
        $this->authorizeAdmin($request);
        $input = $request->validate(['days' => ['nullable', 'integer', Rule::in([7, 30, 90])]]);
        $days = (int) ($input['days'] ?? 30);
        $shop = $request->attributes->get('shop');
        $clock = now($shop->timezone);
        $today = $clock->toDateString();
        $time = $clock->format('H:i:s');
        $from = $clock->copy()->subDays($days - 1)->toDateString();
        $period = ShopAccess::bookings($request)->whereBetween('booking_date', [$from, $today]);
        $confirmed = (clone $period)->where('status', 'confirmed');
        $canReport = in_array('reports.read', ShopAccess::permissions($request->attributes->get('shop_role')), true);
        $statistics = [
            'days' => $days, 'from' => $from, 'to' => $today,
            'confirmed_count' => (clone $confirmed)->count(),
            'cancelled_count' => (clone $period)->where('status', 'cancelled')->count(),
            'booked_values' => $canReport ? (clone $confirmed)->whereNotNull('price')->whereNotNull('currency')
                ->selectRaw('currency, SUM(price) as amount, COUNT(*) as booking_count')->groupBy('currency')->orderBy('currency')->toBase()->get() : null,
            'unpriced_count' => $canReport ? (clone $confirmed)->where(fn ($query) => $query->whereNull('price')->orWhereNull('currency'))->count() : null,
        ];

        return response()->json([
            'today' => $today,
            'timezone' => $shop->timezone,
            'statistics' => $statistics,
            'past_count' => ShopAccess::bookings($request)->where('status', 'confirmed')->where(function ($query) use ($today, $time) {
                $query->where('booking_date', '<', $today)->orWhere(fn ($query) => $query->whereDate('booking_date', $today)->where('booking_time', '<', $time));
            })->count(),
            'today_count' => ShopAccess::bookings($request)->whereDate('booking_date', $today)->where('status', 'confirmed')->count(),
            'upcoming_count' => ShopAccess::bookings($request)->where('status', 'confirmed')->where(function ($query) use ($today, $time) {
                $query->where('booking_date', '>', $today)->orWhere(function ($current) use ($today, $time) {
                    $current->whereDate('booking_date', $today)->where('booking_time', '>=', $time);
                });
            })->count(),
            'cancelled_count' => ShopAccess::bookings($request)->where('status', 'cancelled')->count(),
            'next' => ShopAccess::bookings($request)->where('status', 'confirmed')->where(function ($query) use ($today, $time) {
                $query->where('booking_date', '>', $today)->orWhere(function ($current) use ($today, $time) {
                    $current->whereDate('booking_date', $today)->where('booking_time', '>=', $time);
                });
            })->orderBy('booking_date')->orderBy('booking_time')->limit(5)->get(),
        ])->header('Cache-Control', 'no-store');
    }

    public function profile(Request $request)
    {
        $this->authorizeAdmin($request);

        return response()->json([...ShopAccess::identity($request->user(), $request->attributes->get('shop')->id),
            'joined_at' => $request->user()->created_at])->header('Cache-Control', 'no-store');
    }

    public function updateProfile(Request $request)
    {
        $this->authorizeAdmin($request);
        $input = $request->validate(['name' => 'required|string|max:100']);
        $request->user()->update($input);
        ShopAccess::audit($request, 'profile.updated');

        return $this->profile($request);
    }

    public function password(Request $request)
    {
        $this->authorizeAdmin($request);
        $input = $request->validate(['current_password' => 'required|string|max:200', 'password' => 'required|string|min:12|max:200|confirmed|different:current_password']);
        DB::transaction(function () use ($request, $input) {
            $user = $request->user()->newQuery()->whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            if (! Hash::check($input['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'Your current password is incorrect.']);
            }
            $user->update(['password' => $input['password']]);
            $user->tokens()->delete();
            Password::broker('users')->deleteToken($user);
            ShopAccess::audit($request, 'password.changed');
        });

        return response()->json(['message' => 'Password updated. Sign in again with your new password.']);
    }

    public function invitations(Request $request)
    {
        $shop = ShopAccess::authorize($request, 'members.invite');

        return response()->json([
            'email_ready' => config('mail.default') === 'smtp' && (bool) config('mail.mailers.smtp.username') && (bool) config('mail.mailers.smtp.password'),
            'invitations' => DB::table('admin_invitations')->where('shop_id', $shop->id)->select('id', 'email', 'role', 'barber_id', 'expires_at', 'used_at')->orderByDesc('id')->limit(50)->get(),
            'now' => now()->toIso8601String(),
            'barbers' => collect(config('booking.barbers'))->map(fn ($name, $id) => ['id' => $id, 'name' => $name])->values(),
        ])->header('Cache-Control', 'no-store');
    }

    public function invite(Request $request)
    {
        $shop = ShopAccess::authorize($request, 'members.invite');
        $request->merge(['email' => is_string($request->email) ? strtolower(trim($request->email)) : $request->email]);
        $input = $request->validate(['email' => 'required|email|max:150|unique:users,email', 'send_email' => 'required|boolean',
            'role' => 'sometimes|in:manager,barber', 'barber_id' => ['required_if:role,barber', 'nullable', 'integer', Rule::in(array_keys(config('booking.barbers')))]]);
        if ($input['send_email'] && (config('mail.default') !== 'smtp' || ! config('mail.mailers.smtp.username') || ! config('mail.mailers.smtp.password'))) {
            return response()->json(['message' => 'Email is not connected yet. Create a private invitation link instead, or configure SMTP first.'], 503);
        }
        $token = Str::random(64);
        $expires = now()->addHours(48);
        $id = DB::table('admin_invitations')->insertGetId(['email' => $input['email'], 'token_hash' => hash('sha256', $token), 'expires_at' => $expires,
            'shop_id' => $shop->id, 'role' => $input['role'] ?? 'manager', 'barber_id' => ($input['role'] ?? '') === 'barber' ? $input['barber_id'] : null]);
        $link = rtrim(config('admin.frontend_url'), '/').'/#/admin/signup?'.http_build_query(['email' => $input['email'], 'invitation' => $token]);
        if ($input['send_email']) {
            try {
                Mail::raw("You have been invited to administer Forge & Fade.\n\nCreate your account: ".$link."\n\nThis private, single-use link expires in 48 hours and only works for ".$input['email'].'. Only accept if you expected this invitation.', function ($message) use ($input) {
                    $message->to($input['email'])->subject('Your Forge & Fade admin invitation');
                });
            } catch (\Throwable $exception) {
                DB::table('admin_invitations')->where('id', $id)->delete();

                return response()->json(['message' => 'The email could not be sent. No usable invitation was retained. Check your email settings and retry.'], 503);
            }
        }

        ShopAccess::audit($request, 'invitation.created', $id);

        return response()->json(['message' => $input['send_email'] ? 'Invitation accepted by the email server. Ask the recipient to check their inbox and spam folder.' : 'Private invitation created. Share it only with the named recipient.', 'link' => $link, 'expires_at' => $expires], 201)->header('Cache-Control', 'no-store');
    }
}
