<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['booking', 'contact'] as $form) {
            RateLimiter::for('public-'.$form, function (Request $request) use ($form) {
                $key = hash('sha256', $request->ip() ?? 'unknown');
                $respond = fn (Request $request, array $headers) => response()->json([
                    'message' => 'Too many submissions. Please wait before trying again.',
                ], 429, $headers);

                return [
                    Limit::perMinute(config('public_forms.'.$form.'_per_minute'))->by('minute:'.$key)->response($respond),
                    Limit::perHour(config('public_forms.'.$form.'_per_hour'))->by('hour:'.$key)->response($respond),
                ];
            });
        }
        RateLimiter::for('public-availability', fn (Request $request) => Limit::perMinute(config('public_forms.availability_per_minute'))
            ->by(hash('sha256', $request->ip() ?? 'unknown'))
            ->response(fn (Request $request, array $headers) => response()->json([
                'message' => 'Availability has been checked too often. Please wait a moment and retry.',
            ], 429, $headers)));
    }
}
