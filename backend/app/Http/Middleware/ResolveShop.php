<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\ShopAccess;
use App\Services\WebsiteAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveShop
{
    public function handle(Request $request, Closure $next, string $mode = 'public'): Response
    {
        if ($mode === 'admin') {
            $selected = $this->shopContext($request);
            $shop = Shop::findOrFail($selected->id);
        } else {
            $input = $request->validate(['shop' => 'sometimes|string|max:80|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'], [], ['shop' => 'business identifier']);
            $shop = Shop::where('slug', $input['shop'] ?? 'forge-and-fade')->firstOrFail();
        }
        $request->attributes->set('shop', $shop);

        if ($request->isMethodSafe()) {
            $this->checkStatus($request, $shop, $mode);

            return $next($request);
        }

        return DB::transaction(function () use ($request, $shop, $mode, $next) {
            $current = Shop::whereKey($shop->id)->lockForUpdate()->firstOrFail();
            $request->attributes->set('shop', $current);
            if ($mode === 'admin') {
                $request->user()->refresh();
                $this->shopContext($request, $current->id);
            }
            $this->checkStatus($request, $current, $mode);

            return $next($request);
        }, 5);
    }

    private function shopContext(Request $request, ?int $shopId = null): Shop
    {
        $user = WebsiteAccess::authorizeAccount($request);
        ['shop' => $shop, 'membership' => $membership, 'role' => $role, 'permissions' => $permissions]
            = ShopAccess::context($user, $shopId ?? ShopAccess::requestedShop($request));
        abort_unless($shop && $permissions, 403);
        $request->attributes->set('membership', $membership);
        $request->attributes->set('shop_role', $role);
        $request->attributes->set('shop_permissions', $permissions);

        return $shop;
    }

    private function checkStatus(Request $request, Shop $shop, string $mode): void
    {
        if ($shop->status === 'active') {
            return;
        }
        if ($mode === 'public') {
            abort_unless(in_array($request->path(), ['api/bookings/catalog', 'api/bookings/lookup', 'api/bookings/cancel'], true), 404, 'This business is not accepting online requests.');

            return;
        }
        $personal = in_array($request->path(), ['api/admin/profile', 'api/admin/password'], true);
        $cancelling = $request->isMethod('PATCH') && preg_match('#^api/admin/bookings/\d+$#', $request->path()) && $request->input('action') === 'cancel';
        abort_unless($request->isMethodSafe() || $personal || $cancelling, 403, 'This business is suspended. New bookings and operational changes are paused. Existing appointments can still be viewed or cancelled.');
    }
}
