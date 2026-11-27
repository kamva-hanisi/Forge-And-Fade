<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopAccess;
use App\Services\WebsiteAccess;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Normalizer;

class ShopRegistrationController extends Controller
{
    private const BUSINESS_TIMEZONE = 'Africa/Johannesburg';

    public function store(Request $request)
    {
        $request->merge(['email' => is_string($request->email) ? strtolower(trim($request->email)) : $request->email]);
        $input = $request->validate(['shop_name' => 'required|string|max:100',
            'name' => 'required|string|max:100', 'email' => 'required|email|max:150|unique:users,email',
            'password' => 'required|string|min:12|max:200|confirmed'], [], [
                'shop_name' => 'business name',
            ]);
        try {
            return DB::transaction(function () use ($input) {
                $this->lockRegistration();
                $user = new User(['name' => $input['name'], 'email' => $input['email'], 'password' => $input['password']]);
                $user->is_admin = true;
                $user->save();
                $shop = $this->createBusiness($input['shop_name'], $user);

                return response()->json(['user' => ShopAccess::identity($user),
                    'token' => $user->createToken('shop-dashboard', ['admin'], now()->addHours(8))->plainTextToken], 201)
                    ->header('Cache-Control', 'no-store');
            }, 5);
        } catch (UniqueConstraintViolationException $exception) {
            if (User::where('email', $input['email'])->exists()) {
                throw ValidationException::withMessages(['email' => 'That email is already registered.']);
            }
            throw $exception;
        }
    }

    public function addBusiness(Request $request)
    {
        $message = 'Only the Website Owner can add a business from Platform Overview.';
        $owner = WebsiteAccess::authorizeAccount($request, $message);
        abort_unless(WebsiteAccess::role($owner) === 'owner', 403, $message);
        $input = $request->validate(['shop_name' => 'required|string|max:100'], [], ['shop_name' => 'business name']);

        return DB::transaction(function () use ($input, $owner, $message) {
            $this->lockRegistration();
            $owner->refresh();
            abort_unless(WebsiteAccess::role($owner) === 'owner', 403, $message);
            $shop = $this->createBusiness($input['shop_name'], $owner);

            return response()->json([
                'business' => $shop->only(['id', 'name', 'slug']),
                'shops' => ShopAccess::identity($owner)['shops'],
            ], 201)->header('Cache-Control', 'no-store, private');
        }, 5);
    }

    private function lockRegistration(): void
    {
        // Serialize identifier allocation across registrations.
        $registration = DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
        abort_unless($registration, 503, 'Business registration is not configured.');
    }

    private function createBusiness(string $name, User $owner): Shop
    {
        $shop = Shop::create(['name' => $name, 'slug' => $this->allocateIdentifier($name),
            'timezone' => self::BUSINESS_TIMEZONE, 'settings' => [
                'services' => [], 'barbers' => [], 'hours' => config('booking.hours'),
                'breaks' => config('booking.breaks'), 'closed_dates' => [],
            ]]);
        DB::table('shop_memberships')->insert(['shop_id' => $shop->id, 'user_id' => $owner->id,
            'role' => 'owner', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('audit_events')->insert(['shop_id' => $shop->id, 'user_id' => $owner->id,
            'action' => 'shop.registered', 'created_at' => now()]);

        return $shop;
    }

    private function allocateIdentifier(string $name): string
    {
        $name = Normalizer::normalize($name, Normalizer::FORM_KD) ?: $name;
        $letters = preg_replace('/[^a-z]/', '', strtolower($name));
        $first = str_pad(substr($letters, 0, 2), 2, 'x');
        $last = str_pad(substr($letters, -2), 2, 'x');
        $year = now(self::BUSINESS_TIMEZONE)->year;
        $base = "$first-$year-$last";
        $used = Shop::where('slug', $base)->orWhere('slug', 'like', $base.'-%')->pluck('slug')
            ->merge(DB::table('retired_shop_slugs')->where('slug', $base)->orWhere('slug', 'like', $base.'-%')->pluck('slug'))
            ->flip();
        $identifier = $base;
        for ($suffix = 2; $used->has($identifier); $suffix++) {
            $identifier = "$base-$suffix";
        }

        return $identifier;
    }

    public function profile(Request $request)
    {
        $permission = ShopAccess::allows($request, 'shop.write') ? 'shop.write' : 'bookings.read';
        $shop = ShopAccess::authorize($request, $permission);

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
