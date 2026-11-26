<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WebsiteAccess
{
    public const ADMIN_PERMISSIONS = ['bookings.read', 'bookings.write', 'messages.manage', 'reports.read', 'payments.record'];

    public const SUPER_ADMIN_PERMISSIONS = [...self::ADMIN_PERMISSIONS, 'shop.write', 'shop.manage'];

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
}
