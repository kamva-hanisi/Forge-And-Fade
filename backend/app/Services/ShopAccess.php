<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopAccess
{
    public static function permissions(string $role): array
    {
        return match ($role) {
            'owner', 'platform_admin', 'website_owner' => [...WebsiteAccess::SUPER_ADMIN_PERMISSIONS, 'members.invite', 'members.manage', 'payments.void'],
            'manager' => ['bookings.read', 'bookings.write', 'messages.manage', 'reports.read', 'payments.read', 'payments.record'],
            'barber' => ['bookings.read'],
            default => [],
        };
    }

    public static function authorize(Request $request, string $permission): Shop
    {
        WebsiteAccess::authorizeAccount($request);
        abort_unless(self::allows($request, $permission), 403);

        return $request->attributes->get('shop');
    }

    public static function allows(Request $request, string $permission): bool
    {
        return in_array($permission, $request->attributes->get('shop_permissions', []), true);
    }

    public static function bookings(Request $request)
    {
        $shop = self::authorize($request, 'bookings.read');
        $query = Booking::where('shop_id', $shop->id);
        if ($request->attributes->get('shop_role') === 'barber') {
            $query->where('barber_id', $request->attributes->get('membership')->barber_id);
        }

        return $query;
    }

    public static function context(User $user, ?int $shopId = null): array
    {
        $memberships = DB::table('shop_memberships')->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'manager', 'barber'])
            ->where(fn ($query) => $query->where('role', '!=', 'barber')->orWhere('barber_id', '>', 0))
            ->orderBy('id')->get()->keyBy('shop_id');
        $websiteRole = WebsiteAccess::role($user);
        $globalRole = $websiteRole === 'owner' ? 'website_owner' : (WebsiteAccess::legacyPlatform($user) ? 'platform_admin' : null);
        $assignments = in_array($websiteRole, ['super_admin', 'admin'], true) ? WebsiteAccess::assignments($user) : [];
        $shops = Shop::query()->when(! $globalRole, fn ($query) => $query->whereIn('id', $memberships->keys()->merge(array_keys($assignments))->unique()))
            ->orderBy('name')->orderBy('id')->get(['id', 'name', 'slug'])->keyBy('id');
        $shopId ??= $memberships->keys()->first() ?? array_key_first($assignments) ?? ($shops->has(1) ? 1 : $shops->keys()->first());
        $shop = $shops->get($shopId);
        $membership = $memberships->get($shopId);
        $grant = $assignments[$shopId] ?? [];
        $role = $globalRole ?? ($membership->role ?? ($grant ? 'website_staff' : ''));
        if ($role === 'barber' && in_array('bookings.read', $grant, true)) {
            $role = 'website_staff';
        }
        $permissions = $shop ? array_values(array_unique([...self::permissions($role), ...$grant])) : [];

        return compact('shop', 'shops', 'membership', 'role', 'permissions');
    }

    public static function identity(User $user, ?int $shopId = null): array
    {
        ['shop' => $shop, 'shops' => $shops, 'role' => $role, 'permissions' => $permissions] = self::context($user, $shopId);

        return ['name' => $user->name, 'email' => $user->email, 'role' => $role,
            'shop' => $shop, 'shops' => $shops->values(), 'website_role' => WebsiteAccess::role($user),
            ...(WebsiteAccess::role($user) === 'owner' ? ['public_shop_id' => PublicBusiness::current()?->id] : []),
            'platform_access' => WebsiteAccess::canManagePlatform($user), 'permissions' => $permissions];
    }

    public static function requestedShop(Request $request): ?int
    {
        $shop = $request->header('X-Shop-Id');
        abort_unless($shop === null || ctype_digit((string) $shop), 403);

        return $shop === null ? null : (int) $shop;
    }

    public static function accountIdentity(Request $request): array
    {
        $user = WebsiteAccess::authorizeAccount($request);
        $shopId = self::requestedShop($request);
        $identity = self::identity($user, $shopId);
        abort_unless($identity['shop'] || ($shopId === null && $identity['website_role']), 403);
        $request->attributes->set('shop', $identity['shop']);

        return $identity;
    }

    public static function audit(Request $request, string $action, ?int $subject = null, ?array $details = null, ?int $shopId = null): void
    {
        DB::table('audit_events')->insert(['shop_id' => $shopId ?? $request->attributes->get('shop')?->id,
            'user_id' => $request->user()?->id, 'action' => $action, 'subject_id' => $subject,
            'details' => $details === null ? null : json_encode($details, JSON_THROW_ON_ERROR), 'created_at' => now()]);
    }
}
