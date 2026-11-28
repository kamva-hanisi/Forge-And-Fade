<?php

namespace App\Http\Controllers\Api;

use App\Models\Shop;
use App\Services\ShopCatalog;
use Illuminate\Http\Request;

class ShopLocationController
{
    public function index(Request $request)
    {
        $input = $request->validate(['shop' => 'sometimes|required|string|max:80|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']);
        $locations = Shop::query()->where('status', 'active')
            ->when(isset($input['shop']), fn ($query) => $query->where('slug', $input['shop']))
            ->orderBy('name')->orderBy('slug')->get()
            ->map(function (Shop $shop): array {
                $settings = $shop->settings ?? [];
                $today = now($shop->timezone)->toDateString();
                $closures = collect($settings['closed_dates'] ?? config('booking.closed_dates', []))
                    ->filter(fn (string $date) => $date >= $today)->sort()->take(3)->values();

                return $shop->only(['name', 'slug', 'address', 'phone', 'timezone']) + [
                    'directions_url' => $settings['directions_url'] ?? null,
                    'hours' => $settings['hours'] ?? config('booking.hours'),
                    'upcoming_closures' => $closures,
                    'cover' => $settings['branding']['cover'] ?? null,
                    'can_book' => ShopCatalog::services($shop) !== [] && ShopCatalog::barbers($shop) !== [],
                ];
            });

        return response()->json(['locations' => $locations])->header('Cache-Control', 'no-store');
    }
}
