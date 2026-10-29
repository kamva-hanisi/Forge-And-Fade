<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 80)->unique();
            $table->string('timezone', 64)->default('Africa/Johannesburg');
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
        });
        DB::table('shops')->insert(['id' => 1, 'name' => 'Forge & Fade', 'slug' => 'forge-and-fade',
            'timezone' => 'Africa/Johannesburg', 'address' => 'Cape Town, South Africa', 'phone' => '+27 65 136 0694',
            'created_at' => now(), 'updated_at' => now()]);
        Schema::create('shop_memberships', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->foreignId('shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->unsignedBigInteger('barber_id')->nullable();
            $table->timestamps();
            $table->unique(['shop_id', 'user_id']);
        });
        $first = true;
        foreach (DB::table('users')->where('is_admin', true)->orderBy('id')->cursor() as $user) {
            DB::table('shop_memberships')->insert(['shop_id' => 1, 'user_id' => $user->id,
                'role' => $first ? 'owner' : 'manager', 'created_at' => now(), 'updated_at' => now()]);
            $first = false;
        }
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_platform_admin')->default(false);
        });
        foreach (['bookings', 'contact_messages', 'admin_invitations'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('shop_id')->default(1)->index();
            });
        }
        Schema::table('admin_invitations', function (Blueprint $table) {
            $table->string('role', 20)->default('manager');
            $table->unsignedBigInteger('barber_id')->nullable();
        });
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['shop_id', 'barber_id', 'booking_date', 'status'], 'shop_booking_availability_index');
        });
        Schema::create('shop_booking_day_locks', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->unsignedBigInteger('shop_id');
            $table->unsignedBigInteger('barber_id');
            $table->date('booking_date');
            $table->primary(['shop_id', 'barber_id', 'booking_date']);
        });
        Schema::create('audit_events', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->unsignedBigInteger('shop_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('action', 80);
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        if (DB::table('shops')->where('id', '!=', 1)->exists()) {
            throw new RuntimeException('Cannot remove shop isolation while additional shops exist. Restore a complete backup instead.');
        }
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('shop_booking_day_locks');
        Schema::table('bookings', fn (Blueprint $table) => $table->dropIndex('shop_booking_availability_index'));
        Schema::table('admin_invitations', function (Blueprint $table) {
            $table->dropColumn(['role', 'barber_id']);
        });
        foreach (['bookings', 'contact_messages', 'admin_invitations'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['shop_id']);
                $table->dropColumn('shop_id');
            });
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_platform_admin'));
        Schema::dropIfExists('shop_memberships');
        Schema::dropIfExists('shops');
    }
};
