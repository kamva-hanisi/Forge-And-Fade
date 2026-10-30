<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('shops')->orderBy('id')->pluck('id') as $id) {
            DB::transaction(function () use ($id) {
                $shop = DB::table('shops')->where('id', $id)->lockForUpdate()->first();
                $settings = $shop->settings ? json_decode($shop->settings, true, 512, JSON_THROW_ON_ERROR) : [];
                $settings['services'] ??= config('booking.services');
                $settings['barbers'] ??= array_map(fn ($name) => ['name' => $name, 'active' => true], config('booking.barbers'));
                $settings['hours'] ??= config('booking.hours');
                $settings['breaks'] ??= config('booking.breaks');
                $settings['closed_dates'] ??= config('booking.closed_dates', []);
                DB::table('shops')->where('id', $id)->update(['settings' => json_encode($settings, JSON_THROW_ON_ERROR)]);
            }, 5);
        }
    }

    public function down(): void
    {
        throw new RuntimeException('Shop catalog snapshots cannot safely be removed. Restore a complete backup instead.');
    }
};
