<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retired_shop_slugs', function (Blueprint $table) {
            $table->string('slug', 80)->primary();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retired_shop_slugs');
    }
};
