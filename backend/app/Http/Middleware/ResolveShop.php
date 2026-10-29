<?php

namespace App\Http\Middleware;

use App\Models\Shop;
use App\Services\ShopAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ResolveShop
{
    public function handle(Request $request, Closure $next, string $mode = 'public'): Response
    {
        if ($mode === 'admin') {
            abort_unless($request->user()?->is_admin && $request->user()->tokenCan('admin'), 403);
            $memberships = DB::table('shop_memberships')->where('user_id', $request->user()->id);
            $requested = $request->header('X-Shop-Id');
            if ($requested !== null) {
                abort_unless(ctype_digit((string) $requested), 403);
                $memberships->where('shop_id', $requested);
            }
            $membership = $memberships->orderBy('id')->first();
            if ($request->user()->is_platform_admin) {
                $shop = Shop::findOrFail($requested ?? $membership?->shop_id ?? 1);
                $role = 'platform_admin';
            } else {
                abort_unless($membership && count(ShopAccess::permissions($membership->role)) > 0, 403);
                abort_if($membership->role === 'barber' && ! $membership->barber_id, 403);
                $shop = Shop::findOrFail($membership->shop_id);
                $role = $membership->role;
            }
            $request->attributes->set('membership', $membership);
            $request->attributes->set('shop_role', $role);
        } else {
            $input = $request->validate(['shop' => 'sometimes|string|max:80|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']);
            $shop = Shop::where('slug', $input['shop'] ?? 'forge-and-fade')->firstOrFail();
        }
        $request->attributes->set('shop', $shop);

        return $next($request);
    }
}
