<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Shop;
use App\Services\BookingSchedule;
use App\Services\ShopAccess;
use App\Services\ShopCatalog;
use App\Services\ShopMedia;
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
            'shop_status' => $shop->status,
            'branding' => (object) ($settings['branding'] ?? []),
            'services' => collect(ShopCatalog::services($shop, true))->map(fn ($item, $id) => ['id' => $id, 'active' => true, ...$item])->values(),
            'barbers' => collect(ShopCatalog::barbers($shop, true))->map(fn ($item, $id) => ['id' => $id, ...$item])->values(),
            'schedules' => $schedules,
            'timezone' => $shop->timezone,
            'business_schedule' => [
                'hours' => array_replace(array_fill(1, 7, null), $settings['hours'] ?? config('booking.hours')),
                'breaks' => $settings['breaks'] ?? config('booking.breaks'),
                'closed_dates' => $settings['closed_dates'] ?? config('booking.closed_dates', []),
            ],
            'members' => ShopAccess::allows($request, 'members.manage') ? DB::table('shop_memberships')->join('users', 'users.id', '=', 'shop_memberships.user_id')
                ->where('shop_memberships.shop_id', $shop->id)->orderBy('shop_memberships.id')
                ->get(['shop_memberships.id', 'users.name', 'users.email', 'users.is_platform_admin', 'shop_memberships.role', 'shop_memberships.barber_id']) : [],
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
                'price' => 'required|numeric|min:0|max:99999999.99|decimal:0,2', 'active' => 'required|boolean',
                'description' => 'sometimes|nullable|string|max:360']);
            $items = ShopCatalog::services($shop, true);
            abort_if($service !== null && ! isset($items[$service]), 404);
            abort_if($service === null && count($items) >= 200, 422, 'The service limit is 200.');
            $id = $service ?? (max([0, ...array_keys($items)]) + 1);
            $items[$id] = [...($items[$id] ?? []), ...$input, 'price' => number_format((float) $input['price'], 2, '.', ''), 'active' => (bool) $input['active']];
            $settings['services'] = $items;
            ShopAccess::audit($request, 'service.saved', $id);
        });
    }

    public function barber(Request $request, ?int $barber = null)
    {
        return $this->change($request, function ($shop, &$settings) use ($request, $barber) {
            $input = $request->validate(['name' => 'required|string|max:100', 'active' => 'required|boolean',
                'bio' => 'sometimes|nullable|string|max:360']);
            $items = ShopCatalog::barbers($shop, true);
            abort_if($barber !== null && ! isset($items[$barber]), 404);
            abort_if($barber === null && count($items) >= 200, 422, 'The barber limit is 200.');
            $id = $barber ?? (max([0, ...array_keys($items)]) + 1);
            $items[$id] = [...($items[$id] ?? []), ...$input, 'active' => (bool) $input['active']];
            $settings['barbers'] = $items;
            ShopAccess::audit($request, 'barber.saved', $id);
        });
    }

    public function branding(Request $request)
    {
        return $this->change($request, function ($shop, &$settings) use ($request) {
            $input = $request->validate(['accent' => ['present', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/']]);
            $settings['branding']['accent'] = $input['accent'];
            ShopAccess::audit($request, 'shop.branding_saved', $shop->id);
        });
    }

    public function media(Request $request)
    {
        return $this->change($request, function ($shop, &$settings) use ($request) {
            $input = $request->validate([
                'target' => ['required', Rule::in(['logo', 'cover', 'portfolio'])],
                'barber_id' => 'required_if:target,portfolio|integer|min:1',
                'photo' => ['required_without:remove', 'prohibits:remove', 'file', 'image',
                    'mimetypes:image/jpeg,image/png,image/webp', 'max:2048', 'dimensions:max_width=3200,max_height=3200'],
                'caption' => 'nullable|string|max:80',
                'remove' => ['sometimes', 'string', 'regex:/^[a-f0-9-]{36}\.(jpg|png|webp)$/'],
            ]);
            $target = $input['target'];
            $remove = $input['remove'] ?? null;

            if ($target === 'portfolio') {
                $barbers = ShopCatalog::barbers($shop, true);
                $id = $input['barber_id'];
                abort_unless(isset($barbers[$id]), 404);
                $portfolio = collect($barbers[$id]['portfolio'] ?? []);
                if ($remove) {
                    abort_unless($portfolio->contains('image', $remove), 404);
                    $portfolio = $portfolio->reject(fn ($photo) => $photo['image'] === $remove);
                } else {
                    abort_if($portfolio->count() >= 6, 422, 'Each barber can have up to six portfolio photos.');
                    $portfolio->push(['image' => ShopMedia::store($shop, $request->file('photo')),
                        'caption' => $input['caption'] ?? null]);
                }
                $barbers[$id]['portfolio'] = $portfolio->values()->all();
                $settings['barbers'] = $barbers;
                ShopAccess::audit($request, 'barber.portfolio_saved', $id);
            } else {
                $previous = $settings['branding'][$target] ?? null;
                if ($remove) {
                    abort_unless($previous === $remove, 404);
                    unset($settings['branding'][$target]);
                } else {
                    $settings['branding'][$target] = ShopMedia::store($shop, $request->file('photo'));
                    $remove = $previous;
                }
                ShopAccess::audit($request, 'shop.branding_saved', $shop->id);
            }
            if ($remove) {
                ShopMedia::removeAfterCommit($shop, $remove);
            }
        });
    }

    public function previewMedia(Request $request, string $image)
    {
        return ShopMedia::show(ShopAccess::authorize($request, 'shop.manage'), $image, true);
    }

    private function scheduleInput(Request $request): array
    {
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

        return $input;
    }

    private function protectAppointments(Shop $shop, BookingSchedule $schedule, ?int $barber = null): void
    {
        $clock = now($shop->timezone);
        $bookings = Booking::where('shop_id', $shop->id)->where('status', 'confirmed')
            ->whereDate('booking_date', '>=', $clock->toDateString());
        if ($barber !== null) {
            $bookings->where('barber_id', $barber);
        }
        foreach ($bookings->cursor() as $booking) {
            if (Carbon::parse($booking->ends_at)->gt($clock)
                && ! $schedule->allowed($booking->booking_date->toDateString(), substr($booking->booking_time, 0, 5), $booking->duration, $booking->barber_id, true)) {
                throw ValidationException::withMessages(['hours' => 'This schedule conflicts with an upcoming or in-progress appointment. Reschedule or cancel that appointment first.']);
            }
        }
    }

    public function businessHours(Request $request, BookingSchedule $schedule)
    {
        return $this->change($request, function ($shop, &$settings) use ($request, $schedule) {
            $settings = array_replace($settings, $this->scheduleInput($request));
            $shop->settings = $settings;
            $this->protectAppointments($shop, $schedule);
            ShopAccess::audit($request, 'shop.business_hours_saved', $shop->id);
        });
    }

    public function schedule(Request $request, int $barber, BookingSchedule $schedule)
    {
        return $this->change($request, function ($shop, &$settings) use ($request, $barber, $schedule) {
            abort_unless(isset(ShopCatalog::barbers($shop, true)[$barber]), 404);
            $input = $this->scheduleInput($request);
            $settings['staff_schedules'][$barber] = $input;
            $shop->settings = $settings;
            $this->protectAppointments($shop, $schedule, $barber);
            ShopAccess::audit($request, 'barber.schedule_saved', $barber);
        });
    }

    public function member(Request $request, int $member)
    {
        ShopAccess::authorize($request, 'members.manage');

        return $this->change($request, function ($shop, &$settings) use ($request, $member) {
            $target = DB::table('shop_memberships')->where('shop_id', $shop->id)->where('id', $member)->lockForUpdate()->first();
            abort_unless($target, 404);
            abort_if(DB::table('users')->where('id', $target->user_id)->where('is_platform_admin', true)->exists(), 403, 'Platform account access is managed through trusted server access.');
            abort_if($target->role === 'owner' || $target->user_id === $request->user()->id, 403, 'Owner and self access cannot be changed here.');
            $before = ['role' => $target->role, 'barber_id' => $target->barber_id];
            $after = ['role' => 'revoked', 'barber_id' => null];
            if (! $request->isMethod('delete')) {
                $input = $request->validate(['role' => ['required', Rule::in(['manager', 'barber'])],
                    'barber_id' => ['required_if:role,barber', 'nullable', 'integer', Rule::in(array_keys(ShopCatalog::barbers()))]]);
                $after = ['role' => $input['role'], 'barber_id' => $input['role'] === 'barber' ? (int) $input['barber_id'] : null];
            }
            if ($before !== $after) {
                DB::table('shop_memberships')->where('id', $target->id)->update([...$after, 'updated_at' => now()]);
                ShopAccess::audit($request, $after['role'] === 'revoked' ? 'member.removed' : 'member.updated', $target->user_id, compact('before', 'after'));
            }
        });
    }
}
