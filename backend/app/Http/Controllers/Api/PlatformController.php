<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopAccess;
use App\Services\ShopCatalog;
use App\Services\ShopMedia;
use App\Services\WebsiteAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlatformController extends Controller
{
    private function authorizeOwner(Request $request, string $message = 'Only the Website Owner can delete a business.'): User
    {
        $user = WebsiteAccess::authorizeAccount($request, $message);
        abort_unless(WebsiteAccess::role($user) === 'owner', 403, $message);

        return $user;
    }

    public function selectPublicBusiness(Request $request, int $shop)
    {
        $message = 'Only the Website Owner can change the public business.';
        $this->authorizeOwner($request, $message);

        return DB::transaction(function () use ($request, $shop, $message) {
            DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
            $request->user()->refresh();
            $owner = $this->authorizeOwner($request, $message);
            $business = Shop::whereKey($shop)->lockForUpdate()->firstOrFail();
            $settings = DB::table('platform_settings')->where('id', 1);
            if ((int) $settings->value('public_shop_id') !== $business->id) {
                $settings->update(['public_shop_id' => $business->id]);
                ShopAccess::audit($request, 'website.public_business_changed', null,
                    ['slug' => $business->slug], $business->id);
            }

            return response()->json(['user' => ShopAccess::identity($owner, $business->id)])
                ->header('Cache-Control', 'no-store, private');
        }, 5);
    }

    private function authorizePlatform(Request $request): void
    {
        $message = 'Platform administrator access is required.';
        abort_unless(WebsiteAccess::canManagePlatform(WebsiteAccess::authorizeAccount($request, $message)), 403, $message);
    }

    public function status(Request $request, int $shop)
    {
        $this->authorizePlatform($request);
        $input = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])],
            'revision' => 'required|integer|min:0', 'confirm_slug' => 'required|string|max:80']);

        return DB::transaction(function () use ($request, $shop, $input) {
            $current = Shop::whereKey($shop)->lockForUpdate()->firstOrFail();
            $request->user()->refresh();
            $this->authorizePlatform($request);
            abort_unless(hash_equals($current->slug, $input['confirm_slug']), 422, 'Type the exact business identifier to confirm this change.');
            abort_if($current->lifecycle_revision !== (int) $input['revision'], 409, 'This business status changed. Refresh the overview before trying again.');
            if ($current->status !== $input['status']) {
                $current->forceFill(['status' => $input['status'], 'lifecycle_revision' => $current->lifecycle_revision + 1])->save();
                ShopAccess::audit($request, $current->status === 'suspended' ? 'shop.suspended' : 'shop.reactivated', $current->id);
            }

            return response()->json(['message' => $current->status === 'suspended' ? 'Business suspended. Existing appointments have not been cancelled.' : 'Business reactivated.',
                'shop' => $current->only(['id', 'slug', 'status', 'lifecycle_revision'])])->header('Cache-Control', 'no-store, private');
        }, 5);
    }

    public function index(Request $request)
    {
        $this->authorizePlatform($request);
        $input = $request->validate(['search' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $search = trim($input['search'] ?? '');
        $shops = Shop::query()->select(['id', 'name', 'slug', 'settings', 'created_at', 'status', 'lifecycle_revision'])
            ->addSelect([
                'booking_count' => Booking::selectRaw('COUNT(*)')->whereColumn('bookings.shop_id', 'shops.id'),
                'confirmed_count' => Booking::selectRaw('COUNT(*)')->whereColumn('bookings.shop_id', 'shops.id')->where('status', 'confirmed'),
                'membership_count' => DB::table('shop_memberships')->selectRaw('COUNT(*)')->whereColumn('shop_memberships.shop_id', 'shops.id')->whereIn('role', ['owner', 'manager', 'barber']),
            ])
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', '%'.$search.'%')->orWhere('slug', 'like', '%'.$search.'%');
                });
            })->orderByDesc('id')->paginate(20);
        $shops->through(fn (Shop $shop) => [
            'id' => $shop->id, 'name' => $shop->name, 'slug' => $shop->slug,
            'registered_at' => $shop->created_at?->toIso8601String(),
            'status' => $shop->status, 'lifecycle_revision' => $shop->lifecycle_revision,
            'booking_count' => (int) $shop->booking_count, 'confirmed_count' => (int) $shop->confirmed_count,
            'membership_count' => (int) $shop->membership_count,
            'active_services' => count(ShopCatalog::services($shop)), 'active_barbers' => count(ShopCatalog::barbers($shop)),
        ]);
        ShopAccess::audit($request, 'platform.overview_viewed');

        return response()->json(['scope' => 'platform', 'summary' => [
            'shops' => Shop::count(), 'admin_accounts' => User::where('is_admin', true)->count(),
            'memberships' => DB::table('shop_memberships')->whereIn('role', ['owner', 'manager', 'barber'])->count(),
            'bookings' => Booking::count(),
        ], 'shops' => $shops])->header('Cache-Control', 'no-store, private');
    }

    public function destroy(Request $request, int $shop)
    {
        $this->authorizeOwner($request);
        $input = $request->validate(['confirm_slug' => 'required|string|max:80'], [], ['confirm_slug' => 'business identifier']);

        return DB::transaction(function () use ($request, $shop, $input) {
            // Use the same lock as account registration and website invitation changes.
            DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
            $request->user()->refresh();
            $owner = $this->authorizeOwner($request);
            $business = Shop::whereKey($shop)->lockForUpdate()->firstOrFail();
            abort_unless(hash_equals($business->slug, $input['confirm_slug']), 422,
                'Type the exact business identifier to confirm deletion.');

            DB::table('retired_shop_slugs')->insert(['slug' => $business->slug]);
            DB::table('booking_payments')->where('shop_id', $shop)->delete();
            foreach (['bookings', 'contact_messages', 'admin_invitations', 'shop_booking_day_locks'] as $table) {
                DB::table($table)->where('shop_id', $shop)->delete();
            }
            DB::table('users')->whereIn('id', DB::table('website_shop_access')->where('shop_id', $shop)->select('user_id'))
                ->increment('website_access_version');
            WebsiteAccess::removePendingBusinessAssignments($shop);
            $business->delete();
            ShopMedia::purgeAfterCommit($business);
            ShopAccess::audit($request, 'shop.deleted', $shop, ['slug' => $business->slug], $shop);

            return response()->json([
                'message' => 'Business permanently deleted.',
                'user' => ShopAccess::identity($owner),
            ])->header('Cache-Control', 'no-store, private');
        }, 5);
    }
}
