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
            'owner', 'platform_admin' => ['bookings.read', 'bookings.write', 'members.invite', 'shop.write'],
            'manager' => ['bookings.read', 'bookings.write'],
            'barber' => ['bookings.read'],
            default => [],
        };
    }

    public static function authorize(Request $request, string $permission): Shop
    {
        abort_unless($request->user()?->is_admin && $request->user()->tokenCan('admin'), 403);
        abort_unless(in_array($permission, self::permissions($request->attributes->get('shop_role', '')), true), 403);

        return $request->attributes->get('shop');
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

    public static function identity(User $user, ?int $shopId = null): array
    {
        $membership = DB::table('shop_memberships')->where('user_id', $user->id)
            ->when($shopId, fn ($query) => $query->where('shop_id', $shopId))->orderBy('id')->first();
        $shop = $membership ? Shop::find($membership->shop_id) : ($user->is_platform_admin ? Shop::find($shopId ?? 1) : null);
        $role = $user->is_platform_admin ? 'platform_admin' : ($membership->role ?? '');

        return ['name' => $user->name, 'email' => $user->email, 'role' => $role,
            'shop' => $shop ? ['id' => $shop->id, 'name' => $shop->name, 'slug' => $shop->slug] : null,
            'permissions' => self::permissions($role)];
    }

    public static function audit(Request $request, string $action, ?int $subject = null): void
    {
        DB::table('audit_events')->insert(['shop_id' => $request->attributes->get('shop')?->id,
            'user_id' => $request->user()?->id, 'action' => $action, 'subject_id' => $subject, 'created_at' => now()]);
    }
}
