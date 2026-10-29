<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
        DB::table('admin_registration')->where('id', 1)->update(['claimed' => true]);
    });
    $this->info('Administrator created. Sign in at /#/admin.');

    return 0;
})->purpose('Create an administrator without default credentials');

Artisan::command('admin:invite {email}', function () {
    $email = strtolower(trim($this->argument('email')));
    $validator = Validator::make(['email' => $email], ['email' => 'required|email|max:150|unique:users,email']);
    if ($validator->fails()) {
        $this->error($validator->errors()->first());

        return 1;
    }
    $token = Str::random(64);
    DB::table('admin_invitations')->insert(['email' => $email, 'token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(48)]);
    $this->info('Invitation for '.$email.' (valid for 48 hours):');
    $this->line($token);

    return 0;
})->purpose('Issue a single-use email-bound admin signup invitation');

use Illuminate\Foundation\Inspiring;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
