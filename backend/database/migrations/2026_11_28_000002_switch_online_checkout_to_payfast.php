<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('paystack_reference', 'payment_reference');
            $table->string('payfast_merchant_id', 8)->nullable();
            $table->string('payfast_transaction_id', 30)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropUnique(['payfast_transaction_id']);
            $table->dropColumn(['payfast_merchant_id', 'payfast_transaction_id']);
            $table->renameColumn('payment_reference', 'paystack_reference');
        });
    }
};
