<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->string('payment_choice', 16)->nullable();
            $table->string('paystack_reference', 40)->nullable()->unique();
            $table->timestamp('payment_hold_until')->nullable();
            $table->timestamp('online_paid_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['paystack_reference']);
            $table->dropColumn(['payment_choice', 'paystack_reference', 'payment_hold_until', 'online_paid_at']);
        });
    }
};
