<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('reference', 32)->nullable()->unique();
            $table->decimal('price', 10, 2)->nullable();
            $table->string('currency', 3)->default('ZAR');
            $table->string('timezone', 64)->default('Africa/Johannesburg');
        });
        DB::table('bookings')->select('id')->orderBy('id')->chunkById(200, function ($bookings) {
            foreach ($bookings as $booking) {
                DB::table('bookings')->where('id', $booking->id)->whereNull('reference')
                    ->update(['reference' => 'FF-'.Str::upper(Str::random(16))]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['reference']);
            $table->dropColumn(['reference', 'price', 'currency', 'timezone']);
        });
    }
};
