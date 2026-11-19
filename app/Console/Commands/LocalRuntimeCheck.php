<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

#[Signature('app:local-runtime-check {--url= : Expected loopback application URL} {--json : Output non-secret JSON}')]
#[Description('Read-only checks for a same-laptop Windows installation; never installs or migrates')]
class LocalRuntimeCheck extends Command
{
    public function handle(): int
    {
        $url = rtrim((string) config('app.url'), '/');
        $parts = parse_url($url);
        $queue = (string) config('queue.default');
        $checks = [
            'application_key' => filled(config('app.key')),
            'debug_disabled' => ! config('app.debug'),
            'loopback_url' => is_array($parts)
                && in_array($parts['host'] ?? '', ['localhost', '127.0.0.1'], true)
                && ($parts['scheme'] ?? '') === 'http'
                && empty($parts['user']) && empty($parts['pass'])
                && empty($parts['query']) && empty($parts['fragment'])
                && in_array($parts['path'] ?? '', ['', '/'], true),
            'matching_url' => ! $this->option('url') || $url === rtrim((string) $this->option('url'), '/'),
            'database_queue' => $queue === 'database',
            'safe_queue_retry_window' => (int) config('queue.connections.database.retry_after') > 60,
            'cleanup_disabled' => ! config('retention.scheduling_enabled'),
            'persistent_cache' => ! in_array(config('cache.default'), ['array', 'null'], true),
            'public_entrypoint' => is_file(public_path('index.php')),
            'ssr_disabled' => ! config('inertia.ssr.enabled'),
            'built_assets' => is_file(public_path('build/manifest.json')) && ! is_file(public_path('hot')),
            'configuration_uncached' => ! app()->configurationIsCached(),
            'mail_transport' => config('mail.default') === 'resend'
                || (app()->environment('testing') && config('mail.default') === 'array'),
            'resend_key_present' => config('mail.default') !== 'resend' || filled(config('services.resend.key')),
        ];

        try {
            DB::connection()->getPdo();
            $checks['database_connected'] = true;
            $checks['required_tables'] = collect(['users', 'jobs', 'failed_jobs', 'email_deliveries', 'migrations'])
                ->every(fn (string $table): bool => Schema::hasTable($table));
            $checks['cache_tables'] = config('cache.default') !== 'database'
                || (Schema::hasTable('cache') && Schema::hasTable('cache_locks'));
            $checks['session_table'] = config('session.driver') !== 'database' || Schema::hasTable('sessions');
            $migrations = collect(glob(database_path('migrations/*.php')) ?: [])
                ->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME));
            $checks['migrations_current'] = Schema::hasTable('migrations')
                && $migrations->diff(DB::table('migrations')->pluck('migration'))->isEmpty();
        } catch (Throwable) {
            $checks['database_connected'] = false;
        }

        $ready = ! in_array(false, $checks, true);
        if ($this->option('json')) {
            $this->line((string) json_encode(['ready' => $ready, 'checks' => $checks], JSON_THROW_ON_ERROR));
        } else {
            foreach ($checks as $name => $passed) {
                $this->line(($passed ? 'PASS ' : 'FAIL ').$name);
            }
        }

        return $ready ? self::SUCCESS : self::FAILURE;
    }
}
