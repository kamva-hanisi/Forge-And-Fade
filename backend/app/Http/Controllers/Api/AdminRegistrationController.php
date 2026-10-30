<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopAccess;
use App\Services\ShopCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminRegistrationController extends Controller
{
    public function status()
    {
        $claimed = DB::table('admin_registration')->where('id', 1)->value('claimed');

        return response()->json(['requires_invitation' => (bool) $claimed || User::where('is_admin', true)->exists()])
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        $request->merge(['email' => is_string($request->email) ? strtolower(trim($request->email)) : $request->email]);
        $input = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:12|max:200|confirmed',
            'invitation' => 'nullable|string|size:64',
        ]);
        try {
            return DB::transaction(function () use ($input) {
                $invitation = null;
                $registration = DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
                abort_unless($registration, 503, 'Admin registration is not configured.');
                if ($registration->claimed || User::where('is_admin', true)->exists()) {
                    $invitation = DB::table('admin_invitations')->where('token_hash', hash('sha256', $input['invitation'] ?? ''))
                        ->where('email', $input['email'])->whereNull('used_at')->where('expires_at', '>', now())->lockForUpdate()->first();
                    if (! $invitation) {
                        throw ValidationException::withMessages(['invitation' => 'A valid, unused invitation for this email is required. Ask the shop owner for a code.']);
                    }
                    if ($invitation->role === 'barber' && ! isset(ShopCatalog::barbers(Shop::findOrFail($invitation->shop_id))[$invitation->barber_id])) {
                        throw ValidationException::withMessages(['invitation' => 'This barber assignment is no longer available. Ask the owner for a new invitation.']);
                    }
                    DB::table('admin_invitations')->where('id', $invitation->id)->update(['used_at' => now()]);
                }
                $user = new User(['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password']]);
                $user->is_admin = true;
                $user->save();
                DB::table('shop_memberships')->insert(['shop_id' => $invitation->shop_id ?? 1, 'user_id' => $user->id,
                    'role' => $invitation->role ?? 'owner', 'barber_id' => $invitation->barber_id ?? null, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('audit_events')->insert(['shop_id' => $invitation->shop_id ?? 1, 'user_id' => $user->id,
                    'action' => 'membership.joined', 'subject_id' => $user->id, 'created_at' => now()]);
                DB::table('admin_registration')->where('id', 1)->update(['claimed' => true]);

                return response()->json([
                    'user' => ShopAccess::identity($user),
                    'token' => $user->createToken('admin-dashboard', ['admin'], now()->addHours(8))->plainTextToken,
                ], 201)->header('Cache-Control', 'no-store');
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => 'An account with this email already exists.']);
        }
    }
}
