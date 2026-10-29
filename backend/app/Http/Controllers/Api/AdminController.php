<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Services\BookingSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function login(Request $request)
    {
        $input = $request->validate(['email' => 'required|email', 'password' => 'required|string|max:200']);
        $user = User::where('email', $input['email'])->first();
        $matches = Hash::check($input['password'], $user?->password ?? '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
        if (! $matches || ! $user?->is_admin) {
            throw ValidationException::withMessages(['email' => 'Invalid administrator credentials.']);
        }

        return response()->json([
            'token' => $user->createToken('admin-dashboard', ['admin'], now()->addHours(8))->plainTextToken,
            'user' => ['name' => $user->name, 'email' => $user->email],
        ])->header('Cache-Control', 'no-store');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->is_admin && $request->user()->tokenCan('admin'), 403);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Signed out.']);
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin($request);
        $input = $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => ['nullable', 'date_format:Y-m-d', ...($request->filled('date_from') ? ['after_or_equal:date_from'] : [])],
            'barber_id' => ['nullable', 'integer', Rule::in(array_keys(config('booking.barbers')))],
            'status' => ['nullable', Rule::in(['confirmed', 'cancelled'])],
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
        $query = Booking::query();
        foreach (['barber_id', 'status'] as $field) {
            if (! empty($input[$field])) {
                $query->where($field, $input[$field]);
            }
        }
        if (! empty($input['date_from'])) {
            $query->where('booking_date', '>=', $input['date_from']);
        }
        if (! empty($input['date_to'])) {
            $query->where('booking_date', '<=', $input['date_to']);
        }
        if (! empty($input['search'])) {
            $query->where(function ($search) use ($input) {
                $search->where('customer_name', 'like', '%'.$input['search'].'%')
                    ->orWhere('email', 'like', '%'.$input['search'].'%');
            });
        }

        return response()->json($query->orderBy('booking_date')->orderBy('booking_time')->orderBy('id')->paginate(20))
            ->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, Booking $booking, BookingSchedule $schedule)
    {
        $this->authorizeAdmin($request);
        $input = $request->validate([
            'action' => ['required', Rule::in(['cancel', 'reschedule'])],
            'barber_id' => ['required_if:action,reschedule', 'integer', Rule::in(array_keys(config('booking.barbers')))],
            'booking_date' => 'required_if:action,reschedule|date_format:Y-m-d|after_or_equal:today',
            'booking_time' => 'required_if:action,reschedule|date_format:H:i',
            'revision' => 'required|integer|min:0',
        ]);

        return DB::transaction(function () use ($booking, $input, $schedule) {
            $days = [[$booking->barber_id, $booking->booking_date->toDateString()]];
            if ($input['action'] === 'reschedule') {
                $days[] = [(int) $input['barber_id'], $input['booking_date']];
            }
            sort($days);
            foreach (array_unique($days, SORT_REGULAR) as [$barber, $date]) {
                $schedule->lockDay($barber, $date);
            }
            $current = Booking::whereKey($booking->id)->lockForUpdate()->firstOrFail();
            if ((int) $current->revision !== (int) $input['revision']
                || $current->barber_id !== $booking->barber_id
                || $current->booking_date->toDateString() !== $booking->booking_date->toDateString()) {
                return response()->json(['message' => 'This appointment changed. Refresh the list before editing it.'], 409);
            }
            $current->revision++;
            if ($input['action'] === 'cancel') {
                $current->update(['status' => 'cancelled']);
            } else {
                if ($current->status !== 'confirmed') {
                    throw ValidationException::withMessages(['action' => 'Cancelled appointments cannot be rescheduled. Create a new booking instead.']);
                }
                $schedule->validateTime($input['booking_date'], $input['booking_time'], $current->duration);
                $others = $schedule->bookings($input['barber_id'], $input['booking_date'], true)->where('id', '!=', $current->id);
                if ($schedule->overlaps($input['booking_time'], $current->duration, $others)) {
                    return response()->json(['message' => 'That time is already booked. Please choose another time.'], 409);
                }
                $current->update([
                    'barber_id' => $input['barber_id'],
                    'barber_name' => config('booking.barbers')[$input['barber_id']],
                    'booking_date' => $input['booking_date'],
                    'booking_time' => $input['booking_time'],
                ]);
            }

            return response()->json(['message' => 'Appointment updated.', 'booking' => $current->fresh()]);
        }, 5);
    }
}
