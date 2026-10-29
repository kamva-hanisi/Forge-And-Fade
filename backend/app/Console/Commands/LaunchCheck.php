<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class LaunchCheck extends Command
{
    protected $signature = 'launch:check';

    protected $description = 'Check production configuration without changing settings or printing secrets';

    public function handle(): int
    {
        $https = function ($value): bool {
            $url = parse_url((string) $value);
            $host = strtolower($url['host'] ?? '');

            return ($url['scheme'] ?? '') === 'https' && $host !== ''
                && ! in_array($host, ['localhost', '127.0.0.1', '::1'])
                && ! str_ends_with($host, '.example') && ! str_ends_with($host, '.invalid')
                && ! isset($url['user']) && ! isset($url['pass']);
        };
        $origins = array_values(config('cors.allowed_origins', []));
        $connection = config('database.connections.'.config('database.default'), []);
        $checks = [
            'Production environment' => app()->environment('production'),
            'Debug output disabled' => config('app.debug') === false,
            'Application key configured' => filled(config('app.key')),
            'Public HTTPS API URL' => $https(config('app.url')),
            'Public HTTPS frontend URL' => $https(config('admin.frontend_url')),
            'Explicit HTTPS CORS origins' => count($origins) > 0 && collect($origins)->every(fn ($origin) => $https($origin)
                && ! isset(parse_url($origin)['query']) && ! isset(parse_url($origin)['fragment'])
                && in_array(parse_url($origin, PHP_URL_PATH), [null, ''], true)),
            'MySQL configured' => config('database.default') === 'mysql',
            'Dedicated database credentials' => filled($connection['username'] ?? null) && ($connection['username'] ?? '') !== 'root'
                && filled($connection['password'] ?? null),
            'Shared rate-limit cache configured' => in_array(config('cache.default'), ['database', 'redis'], true),
            'South Africa business timezone' => config('app.timezone') === 'Africa/Johannesburg',
        ];
        foreach ($checks as $label => $passed) {
            $this->line(($passed ? 'PASS ' : 'FAIL ').$label);
        }
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            $this->warn('Email delivery is not connected. Invitations can still be shared privately.');
        }
        $this->warn('This checks configuration only: verify TLS, trusted proxies, backups, database connectivity, first-owner setup and real browser flows separately.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
