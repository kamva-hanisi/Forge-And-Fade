<?php

namespace App\Services;

use App\Models\Shop;
use Illuminate\Http\Request;

class ShopCatalog
{
    public static function services(?Shop $shop = null, bool $all = false): array
    {
        $shop ??= request()->attributes->get('shop');
        $items = $shop?->settings['services'] ?? ($shop?->id === 1 ? config('booking.services') : []);

        return $all ? $items : array_filter($items, fn ($item) => $item['active'] ?? true);
    }

    public static function barbers(?Shop $shop = null, bool $all = false): array
    {
        $shop ??= request()->attributes->get('shop');
        $items = $shop?->settings['barbers'] ?? ($shop?->id === 1 ? array_map(fn ($name) => ['name' => $name, 'active' => true], config('booking.barbers')) : []);

        return $all ? $items : array_filter($items, fn ($item) => $item['active'] ?? true);
    }

    public static function lock(Request $request): Shop
    {
        $shop = Shop::whereKey($request->attributes->get('shop')->id)->lockForUpdate()->firstOrFail();
        $request->attributes->set('shop', $shop);

        return $shop;
    }
}
