<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('website_admin_invitations', function (Blueprint $table) {
            $table->id();
            $table->string('email', 150)->index();
            $table->char('token_hash', 64)->unique();
            $table->json('assignments');
            $table->foreignId('invited_by')->constrained('users')->cascadeOnDelete();
            $table->dateTime('expires_at');
            $table->dateTime('used_at')->nullable();
            $table->dateTime('revoked_at')->nullable();
            $table->dateTime('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('website_admin_invitations');
    }
};
