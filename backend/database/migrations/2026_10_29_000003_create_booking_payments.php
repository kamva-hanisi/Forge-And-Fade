<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('booking_payments', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('shop_id')->constrained();
            $table->foreignId('booking_id')->constrained();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('method', 20);
            $table->date('received_on');
            $table->unsignedBigInteger('recorded_by');
            $table->timestamp('voided_at')->nullable();
            $table->unsignedBigInteger('voided_by')->nullable();
            $table->string('void_reason', 500)->nullable();
            $table->timestamps();
            $table->index(['shop_id', 'received_on', 'voided_at']);
            $table->index(['booking_id', 'voided_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('booking_payments');
    }
};
