<?php

namespace Tests\Feature;

use Tests\TestCase;

class LaunchCheckTest extends TestCase
{
    public function test_local_configuration_fails_launch_checks_without_printing_secrets(): void
    {
        config(['app.debug' => true, 'database.connections.mysql.password' => 'never-print-this-secret']);
        $this->artisan('launch:check')->expectsOutput('FAIL Debug output disabled')
            ->doesntExpectOutput('never-print-this-secret')->assertFailed();
    }

    public function test_production_configuration_passes_and_warns_when_mail_is_pending(): void
    {
        $this->app->instance('env', 'production');
        config(['app.debug' => false, 'app.key' => 'a-test-key', 'app.url' => 'https://api.forgeandfade.co.za',
            'admin.frontend_url' => 'https://forgeandfade.co.za', 'app.timezone' => 'Africa/Johannesburg',
            'cors.allowed_origins' => ['https://forgeandfade.co.za'], 'database.default' => 'mysql',
            'database.connections.mysql.username' => 'shop_app', 'database.connections.mysql.password' => 'test-only',
            'cache.default' => 'database', 'mail.default' => 'log']);
        $this->artisan('launch:check')->expectsOutput('PASS Production environment')
            ->expectsOutput('Email delivery is not connected. Invitations can still be shared privately.')->assertSuccessful();
    }

    public function test_wildcard_origins_and_local_frontend_urls_fail(): void
    {
        config(['cors.allowed_origins' => ['*'], 'admin.frontend_url' => 'http://localhost:5173']);
        $this->artisan('launch:check')->expectsOutput('FAIL Public HTTPS frontend URL')
            ->expectsOutput('FAIL Explicit HTTPS CORS origins')->assertFailed();
    }
}
