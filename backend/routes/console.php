<?php

use App\Models\Shop;
use App\Models\User;
use App\Services\OutgoingMail;
use App\Services\ShopCatalog;
use App\Services\WebsiteAccess;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

Schedule::command('bookings:notify')->everyMinute()->withoutOverlapping(10)->when(fn () => config('booking_mail.enabled'));
Schedule::command('contact:send-replies')->everyMinute()->withoutOverlapping(10)->when(fn () => OutgoingMail::ready());

Artisan::command('admin:create {email} {--name=Administrator} {--website-only}', function () {
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
        if (! $this->option('website-only')) {
            $ownerExists = DB::table('shop_memberships')->where('shop_id', 1)->where('role', 'owner')->exists();
            DB::table('shop_memberships')->insert(['shop_id' => 1, 'user_id' => $user->id,
                'role' => $ownerExists ? 'manager' : 'owner', 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('admin_registration')->where('id', 1)->update(['claimed' => true]);
    });
    $this->info($this->option('website-only')
        ? 'Account created. Grant a website role before signing in.'
        : 'Administrator created. Sign in at /#/admin.');

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
        $this->error('Choose an existing business, a manager/barber role, and a valid barber assignment when needed.');

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
    $email = strtolower(trim($this->argument('email')));
    $granted = DB::transaction(function () use ($email) {
        DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
        $user = User::where('email', $email)->where('is_admin', true)->lockForUpdate()->first();
        if (! $user || User::where('website_role', 'owner')->exists()) {
            return false;
        }
        $user->forceFill(['is_platform_admin' => true])->save();
        $user->tokens()->delete();
        DB::table('audit_events')->insert(['user_id' => $user->id, 'action' => 'platform.access.granted', 'created_at' => now()]);

        return true;
    });
    if (! $granted) {
        $this->error('An existing administrator is required before setup. After setup, the Website Owner grants access in Settings.');

        return 1;
    }
    $this->info('Platform access granted through the trusted console. Sign in again.');

    return 0;
})->purpose('Grant platform access through trusted server administration only');

Artisan::command('admin:website-owner {email}', function () {
    $email = strtolower(trim($this->argument('email')));
    $created = DB::transaction(function () use ($email) {
        DB::table('admin_registration')->where('id', 1)->lockForUpdate()->first();
        if (User::where('website_role', 'owner')->exists()) {
            return false;
        }
        $owner = User::where('email', $email)->where('is_admin', true)->lockForUpdate()->first();
        if (! $owner || $owner->website_role !== null) {
            return false;
        }
        $before = WebsiteAccess::snapshot($owner);
        $owner->forceFill(['website_role' => 'owner', 'is_platform_admin' => false,
            'website_access_version' => $owner->website_access_version + 1])->save();
        $owner->tokens()->delete();
        DB::table('audit_events')->insert(['subject_id' => $owner->id, 'action' => 'website.owner_initialized',
            'details' => json_encode(['before' => $before, 'after' => WebsiteAccess::snapshot($owner)], JSON_THROW_ON_ERROR),
            'created_at' => now()]);

        return true;
    }, 5);
    if (! $created) {
        $this->error('Choose an existing administrator account before website ownership is initialized.');

        return 1;
    }
    $this->info('Website Owner initialized. Sign in again and review legacy platform accounts in Roles & Permissions.');

    return 0;
})->purpose('Initialize the single Website Owner through trusted server access');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
