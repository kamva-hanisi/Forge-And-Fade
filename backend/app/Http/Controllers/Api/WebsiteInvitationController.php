<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopAccess;
use App\Services\WebsiteAccess;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebsiteInvitationController extends Controller
{
    public function store(Request $request)
    {
        $this->authorizeOwner($request);
        $this->normalizeEmail($request);
        return DB::transaction(function () use ($request) {
            DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
            $actor = $this->authorizeOwner($request, fresh: true);
            $input = $request->validate([
                'email' => 'required|email|max:150',
                ...WebsiteAccess::assignmentRules(),
            ], [], ['assignments.*.shop_id' => 'business']);
            if (User::where('email', $input['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'An account with this email already exists. Use Find account to manage its access.']);
            }
            $assignments = WebsiteAccess::validatedAssignments(['role' => 'super_admin', ...$input], true, []);
            $pending = DB::table('website_admin_invitations')->where('email', $input['email'])
                ->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now());
            abort_if($pending->exists(), 409, 'An invitation is already pending for this email. Revoke it before creating another.');
            $token = Str::random(64);
            $expires = now()->addHours(48);
            $id = DB::table('website_admin_invitations')->insertGetId([
                'email' => $input['email'], 'token_hash' => hash('sha256', $token),
                'assignments' => json_encode((object) $assignments, JSON_THROW_ON_ERROR),
                'invited_by' => $actor->id, 'expires_at' => $expires, 'created_at' => now(),
            ]);
            ShopAccess::audit($request, 'website.invitation.created', $id, ['role' => 'super_admin']);
            $link = rtrim(config('admin.frontend_url'), '/').'/#/admin/signup/super-admin?'.http_build_query([
                'email' => $input['email'], 'invitation' => $token,
            ]);

            return response()->json(['id' => $id, 'link' => $link, 'expires_at' => $expires], 201)->header('Cache-Control', 'no-store');
        }, 5);
    }

    public function revoke(Request $request, int $invitation)
    {
        $this->authorizeOwner($request);

        return DB::transaction(function () use ($request, $invitation) {
            DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
            $this->authorizeOwner($request, fresh: true);
            $revoked = DB::table('website_admin_invitations')->where('id', $invitation)
                ->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())
                ->update(['revoked_at' => now()]);
            abort_unless($revoked, 409, 'This invitation is no longer pending.');
            ShopAccess::audit($request, 'website.invitation.revoked', $invitation, ['role' => 'super_admin']);

            return response()->noContent();
        }, 5);
    }

    public function accept(Request $request)
    {
        $this->normalizeEmail($request);
        $input = $request->validate([
            'email' => 'required|email|max:150',
            'name' => 'required|string|max:100',
            'password' => 'required|string|min:12|max:200|confirmed',
            'invitation' => 'required|string|size:64|alpha_num:ascii',
        ]);
        try {
            return DB::transaction(function () use ($input) {
                DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
                $invitation = DB::table('website_admin_invitations')
                    ->where('token_hash', hash('sha256', $input['invitation']))->where('email', $input['email'])
                    ->whereNull('used_at')->whereNull('revoked_at')->where('expires_at', '>', now())->first();
                if (! $invitation || ! User::whereKey($invitation->invited_by)
                    ->where('is_admin', true)->where('website_role', 'owner')->where('website_suspended', false)->exists()) {
                    throw ValidationException::withMessages(['invitation' => 'This Super Admin invitation is invalid, expired, revoked, or already used.']);
                }
                if (User::where('email', $input['email'])->exists()) {
                    throw ValidationException::withMessages(['email' => 'An account with this email already exists. Ask the Website Owner to grant access to it.']);
                }
                $assignments = json_decode($invitation->assignments, true, flags: JSON_THROW_ON_ERROR);
                if (Shop::whereIn('id', array_keys($assignments))->count() !== count($assignments)) {
                    throw ValidationException::withMessages(['invitation' => 'A business assignment is no longer available. Ask the Website Owner for a new invitation.']);
                }

                $user = new User(['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password']]);
                $user->forceFill(['is_admin' => true, 'website_role' => 'super_admin', 'website_access_version' => 1])->save();
                WebsiteAccess::saveAssignments($user, $assignments);
                DB::table('website_admin_invitations')->where('id', $invitation->id)->update(['used_at' => now()]);
                foreach ($assignments as $shop => $permissions) {
                    DB::table('audit_events')->insert(['shop_id' => $shop, 'user_id' => $user->id, 'subject_id' => $user->id,
                        'action' => 'website.shop_access_changed', 'details' => json_encode([
                            'before' => ['role' => null, 'permissions' => []],
                            'after' => ['role' => 'super_admin', 'permissions' => $permissions],
                        ], JSON_THROW_ON_ERROR), 'created_at' => now()]);
                }
                DB::table('audit_events')->insert(['user_id' => $user->id, 'subject_id' => $user->id,
                    'action' => 'website.invitation.accepted', 'details' => json_encode([
                        'invitation_id' => $invitation->id, 'invited_by' => $invitation->invited_by, 'role' => 'super_admin',
                    ], JSON_THROW_ON_ERROR), 'created_at' => now()]);

                return response()->json([
                    'user' => ShopAccess::identity($user),
                    'token' => $user->createToken('admin-dashboard', ['admin'], now()->addHours(8))->plainTextToken,
                ], 201)->header('Cache-Control', 'no-store');
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['email' => 'An account with this email already exists.']);
        }
    }

    private function authorizeOwner(Request $request, bool $fresh = false): User
    {
        if ($fresh) {
            $request->user()->refresh();
        }
        $actor = WebsiteAccess::authorizeAccount($request);
        abort_unless(WebsiteAccess::role($actor) === 'owner', 403, 'Only the Website Owner can manage Super Admin invitations.');

        return $actor;
    }

    private function normalizeEmail(Request $request): void
    {
        $request->merge(['email' => is_string($request->email) ? strtolower(trim($request->email)) : $request->email]);
    }
}
