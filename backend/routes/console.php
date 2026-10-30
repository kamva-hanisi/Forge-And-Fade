<?php

use App\Models\Shop;
use App\Models\User;
use App\Services\ShopCatalog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Schedule::command('bookings:notify')->everyMinute()->withoutOverlapping(10)->when(fn () => config('booking_mail.enabled'));

Artisan::command('admin:create {email} {--name=Administrator}', function () {
    $password = $this->secret('Choose a password (at least 12 characters)');
    $input = ['email' => $this->argument('email'), 'password' => $password];
    $validator = Validator::make($input, ['email' => 'required|email|unique:users,email', 'password' => 'required|string|min:12|max:200']);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    DB::transaction(function () use ($input) {
        DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
        $user = new User(['name' => $this->option('name'), ...$input]);
        $user->is_admin = true;
        $user->save();
        $ownerExists = DB::table('shop_memberships')->where('shop_id', 1)->where('role', 'owner')->exists();
        DB::table('shop_memberships')->insert(['shop_id' => 1, 'user_id' => $user->id,
            'role' => $ownerExists ? 'manager' : 'owner', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('admin_registration')->where('id', 1)->update(['claimed' => true]);
    });
    $this->info('Administrator created. Sign in at /#/admin.');

    return 0;
})->purpose('Create an administrator without default credentials');

Artisan::command('admin:invite {email} {--shop=forge-and-fade} {--role=manager} {--barber=}', function () {
    $email = strtolower(trim($this->argument('email')));
    $validator = Validator::make(['email' => $email], ['email' => 'required|email|max:150|unique:users,email']);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    $shop = Shop::where('slug', $this->option('shop'))->first();
    $role = $this->option('role');
    if (! $shop || ! in_array($role, ['manager', 'barber'], true)
        || ($role === 'barber' && ! array_key_exists((int) $this->option('barber'), ShopCatalog::barbers($shop)))) {
        $this->error('Choose an existing shop, a manager/barber role, and a valid barber assignment when needed.');

        return 1;
    }
    $token = Str::random(64);
    DB::table('admin_invitations')->insert(['email' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48),
        'shop_id' => $shop->id, 'role' => $role, 'barber_id' => $role === 'barber' ? (int) $this->option('barber') : null]);
    $this->info('Invitation for '.$email.' (valid for 48 hours):');
    $this->line($token);

    return 0;
})->purpose('Issue a single-use email-bound admin signup invitation');

Artisan::command('admin:platform {email}', function () {
    $user = User::where('email', strtolower(trim($this->argument('email'))))->where('is_admin', true)->first();
    if (! $user) {
        $this->error('An existing administrator account is required.');

        return 1;
    }
    DB::transaction(function () use ($user) {
        $user->forceFill(['is_platform_admin' => true])->save();
        $user->tokens()->delete();
        DB::table('audit_events')->insert(['user_id' => $user->id, 'action' => 'platform.access.granted', 'created_at' => now()]);
    });
    $this->info('Platform access granted through the trusted console. Sign in again.');

    return 0;
})->purpose('Grant platform access through trusted server administration only');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
