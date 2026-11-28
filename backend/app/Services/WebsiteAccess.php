<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WebsiteAccess
{
    public const BUSINESS_HOURS_PERMISSION = 'shop.hours.write';

    public const BRANDING_PERMISSION = 'shop.branding.write';

    public const ADMIN_PERMISSIONS = ['bookings.read', 'bookings.write', 'messages.manage', 'reports.read', 'payments.read', 'payments.record', self::BUSINESS_HOURS_PERMISSION];

    public const SUPER_ADMIN_PERMISSIONS = [...self::ADMIN_PERMISSIONS, 'shop.write', 'shop.manage', self::BRANDING_PERMISSION];

    public static function removePendingBusinessAssignments(int $shop): void
    {
        DB::table('website_admin_invitations')->whereNull('used_at')->whereNull('revoked_at')
            ->where('expires_at', '>', now())->whereNotNull('assignments->'.$shop)
            ->select(['id', 'assignments'])->eachById(function ($invitation) use ($shop) {
                $assignments = json_decode($invitation->assignments, true, flags: JSON_THROW_ON_ERROR);
                unset($assignments[$shop]);
                DB::table('website_admin_invitations')->where('id', $invitation->id)
                    ->update(['assignments' => json_encode((object) $assignments, JSON_THROW_ON_ERROR)]);
            });
    }

    public static function role(User $user): ?string
    {
        return $user->website_suspended ? null : $user->website_role;
    }

    public static function legacyPlatform(User $user): bool
    {
        return $user->is_platform_admin && $user->website_role === null
            && ! User::where('website_role', 'owner')->exists();
    }

    public static function canManagePlatform(User $user): bool
    {
        return in_array(self::role($user), ['owner', 'super_admin'], true) || self::legacyPlatform($user);
    }

    public static function authorizeAccount(Request $request, string $message = ''): User
    {
        $user = $request->user();
        abort_unless($user?->is_admin && $user->tokenCan('admin'), 403, $message);

        return $user;
    }

    public static function assignments(User $user): array
    {
        return DB::table('website_shop_access')->where('user_id', $user->id)->orderBy('shop_id')
            ->pluck('permissions', 'shop_id')->map(fn ($permissions) => json_decode($permissions, true))->all();
    }

    public static function snapshot(User $user): array
    {
        return ['role' => $user->website_role, 'suspended' => (bool) $user->website_suspended,
            'legacy_platform' => $user->is_platform_admin,
            'assignments' => (object) self::assignments($user)];
    }

    public static function assignmentRules(): array
    {
        return [
            'assignments' => 'present|array|max:200',
            'assignments.*' => 'required|array:shop_id,permissions',
            'assignments.*.shop_id' => 'required|integer|distinct|exists:shops,id',
            'assignments.*.permissions' => 'required|array|max:'.count(self::SUPER_ADMIN_PERMISSIONS),
            'assignments.*.permissions.*' => ['required', 'string', Rule::in(WebsiteAccess::SUPER_ADMIN_PERMISSIONS)],
        ];
    }

    public static function validatedAssignments(array $input, bool $isOwner, array $scope): array
    {
        $role = $input['role'];
        $rows = $input['assignments'];
        if ($role === null && $rows) {
            throw ValidationException::withMessages(['assignments' => 'Remove all website business assignments when removing the website role.']);
        }
        if ($role === 'admin' && ! $rows) {
            throw ValidationException::withMessages(['assignments' => 'An Admin needs at least one business assignment.']);
        }
        $allowed = $role === 'super_admin' ? WebsiteAccess::SUPER_ADMIN_PERMISSIONS : WebsiteAccess::ADMIN_PERMISSIONS;
        $result = [];
        foreach ($rows as $row) {
            $shop = (int) $row['shop_id'];
            $permissions = array_values(array_intersect($allowed, $row['permissions']));
            abort_if(array_diff($row['permissions'], $allowed)
                || (! $isOwner && array_diff($permissions, $scope[$shop] ?? [])), 403, 'A business or permission is outside your delegation scope.');
            if ((array_intersect(['bookings.write', 'reports.read', 'payments.record'], $permissions)
                    && ! in_array('bookings.read', $permissions, true))
                || (in_array('payments.record', $permissions, true) && ! in_array('payments.read', $permissions, true))) {
                throw ValidationException::withMessages(['assignments' => 'Booking edits and reports require View appointments. Record payments requires View appointments and View payments.']);
            }
            $result[$shop] = $permissions;
        }
        ksort($result);

        return $result;
    }

    public static function saveAssignments(User $target, array $assignments): void
    {
        $query = DB::table('website_shop_access')->where('user_id', $target->id);
        $query->whereNotIn('shop_id', array_keys($assignments))->delete();
        foreach ($assignments as $shop => $permissions) {
            DB::table('website_shop_access')->updateOrInsert(['user_id' => $target->id, 'shop_id' => $shop],
                ['permissions' => json_encode($permissions, JSON_THROW_ON_ERROR)]);
        }
    }
}
