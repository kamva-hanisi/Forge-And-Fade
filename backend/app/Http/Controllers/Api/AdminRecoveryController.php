<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminRecoveryController extends Controller
{
    public function requestLink(Request $request)
    {
        $request->merge(['email' => is_string($request->input('email')) ? Str::lower(trim($request->input('email'))) : $request->input('email')]);
        $input = $request->validate(['email' => ['required', 'email', 'max:150']]);

        if (config('mail.default') !== 'smtp' || ! config('mail.mailers.smtp.username') || ! config('mail.mailers.smtp.password')) {
            return response()->json(['message' => 'Email recovery is not configured. Please contact your shop owner or the server administrator.'], 503);
        }

        try {
            Password::broker('users')->sendResetLink(['email' => $input['email'], 'is_admin' => true]);
        } catch (\Throwable) {
            if ($user = User::where('email', $input['email'])->where('is_admin', true)->first()) {
                Password::broker('users')->deleteToken($user);
            }

            return response()->json(['message' => 'Recovery email is temporarily unavailable. Please try again later.'], 503);
        }

        return response()->json(['message' => 'If this email belongs to an administrator, a recovery link will be sent. Check your inbox and spam folder.']);
    }

    public function reset(Request $request)
    {
        $request->merge(['email' => is_string($request->input('email')) ? Str::lower(trim($request->input('email'))) : $request->input('email')]);
        $input = $request->validate([
            'email' => ['required', 'email', 'max:150'],
            'token' => ['required', 'string', 'size:64'],
            'password' => ['required', 'string', 'min:12', 'max:200', 'confirmed'],
        ]);

        $status = DB::transaction(function () use ($input) {
            User::where('email', $input['email'])->lockForUpdate()->first();

            return Password::broker('users')->reset($input + ['is_admin' => true], function (User $user, string $password) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                $user->tokens()->delete();
                DB::table('audit_events')->insert([
                    'shop_id' => null, 'user_id' => $user->id, 'action' => 'password.reset',
                    'subject_id' => $user->id, 'created_at' => now(),
                ]);
                event(new PasswordReset($user));
            });
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['token' => ['This recovery link is invalid or expired. Please request a new one.']]);
        }

        return response()->json(['message' => 'Your password has been reset. Sign in again with your new password.']);
    }
}
