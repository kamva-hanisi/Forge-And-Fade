<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shop;
use App\Models\User;
use App\Services\ShopAccess;
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
            'password' => 'required|string|min:12|max:200|confirmed']);
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
            throw ValidationException::withMessages(['shop_slug' => 'That shop address or email is already registered. Please check your details.']);
        }
    }

    public function profile(Request $request)
    {
        ShopAccess::authorize($request, 'bookings.read');

        return response()->json($request->attributes->get('shop')->only(['name', 'slug', 'address', 'phone', 'timezone']))
            ->header('Cache-Control', 'no-store');
    }

    public function update(Request $request)
    {
        $shop = ShopAccess::authorize($request, 'shop.write');
        $input = $request->validate(['name' => 'required|string|max:100', 'address' => 'nullable|string|max:255', 'phone' => 'nullable|string|max:30']);
        DB::transaction(function () use ($shop, $input, $request) {
            $shop->update($input);
            ShopAccess::audit($request, 'shop.updated', $shop->id);
        });

        return $this->profile($request);
    }
}
