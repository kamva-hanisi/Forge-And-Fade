<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('lifecycle_revision')->default(0);
        });
    }

    public function down(): void
    {
        if (DB::table('shops')->where('status', '!=', 'active')->exists()) {
            throw new RuntimeException('Cannot remove suspension controls while shops are suspended. Restore a complete backup instead.');
        }
        Schema::table('shops', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn(['status', 'lifecycle_revision']);
        });
    }
};
