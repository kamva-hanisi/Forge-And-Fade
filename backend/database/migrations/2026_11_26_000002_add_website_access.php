<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('website_role', 20)->nullable()->index();
            $table->boolean('website_suspended')->default(false);
            $table->unsignedInteger('website_access_version')->default(0);
        });
        Schema::create('website_shop_access', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->json('permissions');
            $table->primary(['user_id', 'shop_id']);
        });
        Schema::table('audit_events', function (Blueprint $table) {
            $table->json('details')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('audit_events', fn (Blueprint $table) => $table->dropColumn('details'));
        Schema::dropIfExists('website_shop_access');
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['website_role']);
            $table->dropColumn(['website_role', 'website_suspended', 'website_access_version']);
        });
    }
};
