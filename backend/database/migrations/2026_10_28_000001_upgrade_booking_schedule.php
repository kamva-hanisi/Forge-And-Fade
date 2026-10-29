<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_day_locks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('barber_id');
            $table->date('booking_date');
            $table->primary(['barber_id', 'booking_date']);
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique('unique_barber_booking');
            $table->index(['barber_id', 'booking_date', 'status'], 'booking_availability_index');
        });
    }

    public function down(): void
    {
        if (DB::table('bookings')->select('barber_id', 'booking_date', 'booking_time')
            ->groupBy('barber_id', 'booking_date', 'booking_time')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Cannot restore the old unique index while reused cancelled slots exist. Archive duplicate historical rows before rollback.');
        }
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex('booking_availability_index');
            $table->unique(['barber_id', 'booking_date', 'booking_time'], 'unique_barber_booking');
        });
        Schema::dropIfExists('booking_day_locks');
    }
};
