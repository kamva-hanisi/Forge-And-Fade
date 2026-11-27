<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->foreignId('public_shop_id')->nullable()->constrained('shops')->nullOnDelete();
        });
        DB::table('platform_settings')->insert([
            'id' => 1,
            'public_shop_id' => DB::table('shops')->where('slug', 'forge-and-fade')->value('id'),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
