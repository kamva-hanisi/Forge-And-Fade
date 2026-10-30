<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Services\BookingSchedule;
use App\Services\ShopAccess;
use App\Services\ShopCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ShopManagementController extends Controller
{
    public function index(Request $request)
    {
        $shop = ShopAccess::authorize($request, 'shop.manage');
        $settings = $shop->settings ?? [];
        $schedules = [];
        foreach (ShopCatalog::barbers($shop, true) as $id => $barber) {
            $schedules[$id] = $settings['staff_schedules'][$id] ?? [
                'hours' => array_replace(array_fill(1, 7, null), $settings['hours'] ?? config('booking.hours')),
                'breaks' => [], 'closed_dates' => [],
            ];
        }

        return response()->json([
            'version' => $settings['management_revision'] ?? 0,
            'services' => collect(ShopCatalog::services($shop, true))->map(fn ($item, $id) => ['id' => $id, 'active' => true, ...$item])->values(),
            'barbers' => collect(ShopCatalog::barbers($shop, true))->map(fn ($item, $id) => ['id' => $id, ...$item])->values(),
            'schedules' => $schedules,
            'members' => DB::table('shop_memberships')->join('users', 'users.id', '=', 'shop_memberships.user_id')
                ->where('shop_memberships.shop_id', $shop->id)->orderBy('shop_memberships.id')
                ->get(['shop_memberships.id', 'users.name', 'users.email', 'users.is_platform_admin', 'shop_memberships.role', 'shop_memberships.barber_id']),
        ])->header('Cache-Control', 'no-store');
    }

    private function change(Request $request, callable $operation)
    {
        ShopAccess::authorize($request, 'shop.manage');
        $request->validate(['version' => ['required', 'integer', 'min:0']]);

        return DB::transaction(function () use ($request, $operation) {
            $shop = ShopCatalog::lock($request);
            $settings = $shop->settings ?? [];
            if ((int) $request->input('version') !== ($settings['management_revision'] ?? 0)) {
                return response()->json(['message' => 'Shop settings changed. Refresh before saving your changes.'], 409);
            }
            $operation($shop, $settings);
            $settings['management_revision'] = ($settings['management_revision'] ?? 0) + 1;
            $shop->update(['settings' => $settings]);

            return $this->index($request);
        }, 5);
    }

    public function service(Request $request, ?int $service = null)
    {
        return $this->change($request, function ($shop, &$settings) use ($request, $service) {
            $input = $request->validate(['name' => 'required|string|max:100', 'duration' => 'required|integer|min:10|max:480',
                'price' => 'required|numeric|min:0|max:99999999.99|decimal:0,2', 'active' => 'required|boolean']);
            $items = ShopCatalog::services($shop, true);
            abort_if($service !== null && ! isset($items[$service]), 404);
            abort_if($service === null && count($items) >= 200, 422, 'The service limit is 200.');
            $id = $service ?? (max([0, ...array_keys($items)]) + 1);
            $items[$id] = [...$input, 'price' => number_format((float) $input['price'], 2, '.', ''), 'active' => (bool) $input['active']];
            $settings['services'] = $items;
            ShopAccess::audit($request, 'service.saved', $id);
        });
    }

    public function barber(Request $request, ?int $barber = null)
    {
        return $this->change($request, function ($shop, &$settings) use ($request, $barber) {
            $input = $request->validate(['name' => 'required|string|max:100', 'active' => 'required|boolean']);
            $items = ShopCatalog::barbers($shop, true);
            abort_if($barber !== null && ! isset($items[$barber]), 404);
            abort_if($barber === null && count($items) >= 200, 422, 'The barber limit is 200.');
            $id = $barber ?? (max([0, ...array_keys($items)]) + 1);
            $items[$id] = ['name' => $input['name'], 'active' => (bool) $input['active']];
            $settings['barbers'] = $items;
            ShopAccess::audit($request, 'barber.saved', $id);
        });
    }

    public function schedule(Request $request, int $barber, BookingSchedule $schedule)
    {
        return $this->change($request, function ($shop, &$settings) use ($request, $barber, $schedule) {
            abort_unless(isset(ShopCatalog::barbers($shop, true)[$barber]), 404);
            $input = $request->validate([
                'hours' => ['required', 'array:1,2,3,4,5,6,7', 'required_array_keys:1,2,3,4,5,6,7'],
                'hours.*' => 'nullable|array:0,1|size:2|required_array_keys:0,1', 'hours.*.*' => 'required|date_format:H:i',
                'breaks' => 'present|array|max:10', 'breaks.*' => 'required|array:0,1|size:2|required_array_keys:0,1', 'breaks.*.*' => 'required|date_format:H:i',
                'closed_dates' => 'present|array|max:365', 'closed_dates.*' => 'required|date_format:Y-m-d|distinct',
            ]);
            foreach ([...array_values($input['hours']), ...$input['breaks']] as $range) {
                if ($range !== null && $range[0] >= $range[1]) {
                    throw ValidationException::withMessages(['hours' => 'Every opening/break end must be later than its start. Overnight shifts are not supported.']);
                }
            }
            $settings['staff_schedules'][$barber] = $input;
            $shop->settings = $settings;
            $clock = now($shop->timezone);
            foreach (Booking::where('shop_id', $shop->id)->where('barber_id', $barber)->where('status', 'confirmed')
                ->whereDate('booking_date', '>=', $clock->toDateString())->cursor() as $booking) {
                if (Carbon::parse($booking->starts_at)->gt($clock) && ! $schedule->allowed($booking->booking_date->toDateString(), substr($booking->booking_time, 0, 5), $booking->duration, $barber)) {
                    throw ValidationException::withMessages(['hours' => 'This schedule conflicts with an existing future appointment. Reschedule or cancel that appointment first.']);
                }
            }
            ShopAccess::audit($request, 'barber.schedule_saved', $barber);
        });
    }

    public function member(Request $request, int $member)
    {
        return $this->change($request, function ($shop, &$settings) use ($request, $member) {
            $target = DB::table('shop_memberships')->where('shop_id', $shop->id)->where('id', $member)->lockForUpdate()->first();
            abort_unless($target, 404);
            abort_if(DB::table('users')->where('id', $target->user_id)->where('is_platform_admin', true)->exists(), 403, 'Platform account access is managed through trusted server access.');
            abort_if($target->role === 'owner' || $target->user_id === $request->user()->id, 403, 'Owner and self access cannot be changed here.');
            if ($request->isMethod('delete')) {
                DB::table('shop_memberships')->where('id', $target->id)->update(['role' => 'revoked', 'barber_id' => null, 'updated_at' => now()]);
                ShopAccess::audit($request, 'member.removed', $target->user_id);
            } else {
                $input = $request->validate(['role' => ['required', Rule::in(['manager', 'barber'])],
                    'barber_id' => ['required_if:role,barber', 'nullable', 'integer', Rule::in(array_keys(ShopCatalog::barbers()))]]);
                DB::table('shop_memberships')->where('id', $target->id)->update(['role' => $input['role'],
                    'barber_id' => $input['role'] === 'barber' ? $input['barber_id'] : null, 'updated_at' => now()]);
                ShopAccess::audit($request, 'member.updated', $target->user_id);
            }
        });
    }
}
