<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('service_id');
            $table->string('service_name');

            $table->unsignedBigInteger('barber_id');
            $table->string('barber_name');

            $table->date('booking_date');
            $table->time('booking_time');

            $table->unsignedInteger('duration');

            $table->string('customer_name');
            $table->string('email');
            $table->string('phone');

            $table->text('notes')->nullable();

            $table->string('status')->default('confirmed');

            $table->timestamps();

            $table->unique(
                [
                    'barber_id',
                    'booking_date',
                    'booking_time'
                ],
                'unique_barber_booking'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};