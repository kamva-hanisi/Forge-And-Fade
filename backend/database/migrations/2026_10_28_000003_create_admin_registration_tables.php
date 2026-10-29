<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_registration', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedInteger('id')->primary();
            $table->boolean('claimed')->default(false);
        });
        DB::table('admin_registration')->insert(['id' => 1, 'claimed' => DB::table('users')->where('is_admin', true)->exists()]);
        Schema::create('admin_invitations', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('email', 150);
            $table->string('token_hash', 64)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_invitations');
        Schema::dropIfExists('admin_registration');
    }
};
