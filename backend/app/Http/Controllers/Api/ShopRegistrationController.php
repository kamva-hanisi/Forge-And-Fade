<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopAccess;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ShopRegistrationController extends Controller
{
    public function store(Request $request)
    {
        $request->merge(['email' => is_string($request->email) ? strtolower(trim($request->email)) : $request->email]);
        $input = $request->validate(['shop_name' => 'required|string|max:100',
            'shop_slug' => 'required|string|min:3|max:80|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/|unique:shops,slug',
            'name' => 'required|string|max:100', 'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:12|max:200|confirmed'], [], [
                'shop_name' => 'business name',
                'shop_slug' => 'business identifier',
            ]);
        try {
            return DB::transaction(function () use ($input) {
                $shop = Shop::create(['name' => $input['shop_name'], 'slug' => $input['shop_slug'],
                    'timezone' => 'Africa/Johannesburg', 'settings' => [
                        'services' => [], 'barbers' => [], 'hours' => config('booking.hours'),
                        'breaks' => config('booking.breaks'), 'closed_dates' => [],
                    ]]);
                $user = new User(['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password']]);
                $user->is_admin = true;
                $user->save();
                DB::table('shop_memberships')->insert(['shop_id' => $shop->id, 'user_id' => $user->id,
                    'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('audit_events')->insert(['shop_id' => $shop->id, 'user_id' => $user->id,
                    'action' => 'shop.registered', 'created_at' => now()]);

                return response()->json(['user' => ShopAccess::identity($user),
                    'token' => $user->createToken('shop-dashboard', ['admin'], now()->addHours(8))->plainTextToken], 201)
                    ->header('Cache-Control', 'no-store');
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            throw ValidationException::withMessages(['shop_slug' => 'That business address or email is already registered. Please check your details.']);
        }
    }

    public function profile(Request $request)
    {
        $shop = ShopAccess::authorize($request, 'bookings.read');

        return response()->json($shop->only(['name', 'slug', 'address', 'phone', 'timezone']) + [
            'directions_url' => $shop->settings['directions_url'] ?? null,
        ])
            ->header('Cache-Control', 'no-store');
    }

    public function update(Request $request)
    {
        $shop = ShopAccess::authorize($request, 'shop.write');
        $input = $request->validate([
            'name' => 'required|string|max:100',
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'directions_url' => ['bail', 'nullable', 'string', 'max:2048', 'url:https',
                function (string $attribute, string $value, Closure $fail): void {
                    $url = parse_url($value);
                    $host = strtolower($url['host'] ?? '');
                    $path = $url['path'] ?? '';
                    $maps = in_array($host, ['google.com', 'www.google.com'], true)
                        && ($path === '/maps' || str_starts_with($path, '/maps/'));
                    $sharedPin = $host === 'maps.app.goo.gl' && preg_match('#^/[A-Za-z0-9_-]+/?$#D', $path);
                    if ((! $maps && ! $sharedPin) || isset($url['user']) || isset($url['port'])) {
                        $fail('Enter a Google Maps link for your business pin.');
                    }
                }],
        ]);
        DB::transaction(function () use ($shop, $input, $request) {
            if (array_key_exists('directions_url', $input)) {
                $settings = $shop->settings ?? [];
                if ($input['directions_url'] === null) {
                    unset($settings['directions_url']);
                } else {
                    $settings['directions_url'] = $input['directions_url'];
                }
                $input['settings'] = $settings;
                unset($input['directions_url']);
            }
            $shop->update($input);
            ShopAccess::audit($request, 'shop.updated', $shop->id);
        });

        return $this->profile($request);
    }
}
