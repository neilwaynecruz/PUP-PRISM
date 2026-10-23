<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;
use Laravel\Fortify\Features;

#[Signature('app:production-readiness {--json : Output machine-readable JSON}')]
#[Description('Check production configuration without displaying secrets')]
class CheckProductionReadiness extends Command
{
    public function handle(): int
    {
        $checks = $this->checks();
        $hasFailures = collect($checks)->contains(
            fn (array $check): bool => $check['status'] === 'fail',
        );

        if ($this->option('json')) {
            $this->line(json_encode([
                'ready' => ! $hasFailures,
                'checks' => $checks,
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            return $hasFailures ? self::FAILURE : self::SUCCESS;
        }

        $this->components->info('PUP PRISM production readiness check');

        foreach ($checks as $check) {
            $this->components->twoColumnDetail(
                $check['label'],
                sprintf('[%s] %s', strtoupper($check['status']), $check['message']),
            );
        }

        if ($hasFailures) {
            $this->components->error('Production blockers remain. Review failed checks before deployment.');

            return self::FAILURE;
        }

        $this->components->info('No blocking configuration issue was detected. Complete the deployment runbook and staging checks before release.');

        return self::SUCCESS;
    }

    /**
     * @return list<array{key: string, label: string, status: 'pass'|'warning'|'fail', message: string}>
     */
    private function checks(): array
    {
        $appUrl = (string) config('app.url');
        $fortifyFeatures = config('fortify.features', []);

        if (! is_array($fortifyFeatures)) {
            $fortifyFeatures = [];
        }

        return [
            $this->result('environment', 'Application environment', config('app.env') === 'production', 'Production environment is selected.', 'Set APP_ENV=production.'),
            $this->result('debug', 'Debug mode', config('app.debug') === false, 'Debug output is disabled.', 'Set APP_DEBUG=false.'),
            $this->result('application_key', 'Application key', $this->hasValidApplicationKey(), 'A valid application key is configured.', 'Generate and securely store a valid APP_KEY.'),
            $this->result('https_url', 'Application URL', parse_url($appUrl, PHP_URL_SCHEME) === 'https', 'APP_URL uses HTTPS.', 'Set APP_URL to the production HTTPS URL.'),
            $this->result('database', 'Database connection', $this->usesPostgreSql(), 'PostgreSQL is selected.', 'Set the production database connection to a PostgreSQL driver.'),
            $this->result('queue', 'Queue connection', $this->usesPersistentQueue(), 'Queued work uses a persistent backend.', 'Use database, Redis, SQS, or Beanstalkd queues and run a supervised worker.'),
            $this->result('mail', 'Mail transport', $this->usesDeliveryCapableMailer(), 'A delivery-capable mail transport is selected.', 'Configure an approved production mail transport without log or array fallbacks.'),
            $this->result('session_encryption', 'Session encryption', config('session.encrypt') === true, 'Session payloads are encrypted.', 'Set SESSION_ENCRYPT=true.'),
            $this->result('secure_cookie', 'Secure session cookie', config('session.secure') === true, 'Session cookies require HTTPS.', 'Set SESSION_SECURE_COOKIE=true.'),
            $this->result('http_only_cookie', 'HTTP-only session cookie', config('session.http_only') === true, 'Browser scripts cannot read the session cookie.', 'Set SESSION_HTTP_ONLY=true.'),
            $this->result('same_site_cookie', 'SameSite session cookie', in_array(config('session.same_site'), ['lax', 'strict'], true), 'The session cookie has a CSRF-resistant SameSite policy.', 'Use SESSION_SAME_SITE=lax or strict.'),
            $this->result('registration', 'Public registration', ! in_array(Features::registration(), $fortifyFeatures, true), 'Public self-registration is disabled.', 'Set REGISTRATION_ENABLED=false unless institutionally approved.'),
            $this->result('asset_manifest', 'Production assets', is_file(public_path('build/manifest.json')), 'The Vite production manifest exists.', 'Run npm ci and npm run build.'),
            $this->result('storage', 'Writable application storage', $this->runtimeDirectoriesAreWritable(), 'Runtime storage directories are writable.', 'Grant the application user write access to storage/framework, storage/logs, and bootstrap/cache.'),
            $this->warning('error_monitoring', 'Error monitoring', filled(config('sentry.dsn')), 'Sentry is configured.', 'No Sentry DSN is configured; ensure another monitored error channel is active.'),
            $this->warning('realtime', 'Realtime broadcasting', config('broadcasting.default') === 'reverb', 'Laravel Reverb is selected.', 'Realtime delivery is disabled; document this if Reverb is intentionally not deployed.'),
        ];
    }

    /**
     * @return array{key: string, label: string, status: 'pass'|'fail', message: string}
     */
    private function result(
        string $key,
        string $label,
        bool $passed,
        string $passedMessage,
        string $failedMessage,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $passed ? 'pass' : 'fail',
            'message' => $passed ? $passedMessage : $failedMessage,
        ];
    }

    /**
     * @return array{key: string, label: string, status: 'pass'|'warning', message: string}
     */
    private function warning(
        string $key,
        string $label,
        bool $passed,
        string $passedMessage,
        string $warningMessage,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $passed ? 'pass' : 'warning',
            'message' => $passed ? $passedMessage : $warningMessage,
        ];
    }

    private function hasValidApplicationKey(): bool
    {
        $key = config('app.key');
        $cipher = config('app.cipher');

        if (! is_string($key) || $key === '' || ! is_string($cipher)) {
            return false;
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if (! is_string($decoded)) {
                return false;
            }

            $key = $decoded;
        }

        return Encrypter::supported($key, $cipher);
    }

    private function usesPostgreSql(): bool
    {
        $connection = config('database.default');

        return is_string($connection)
            && config("database.connections.{$connection}.driver") === 'pgsql';
    }

    private function usesPersistentQueue(): bool
    {
        $connection = config('queue.default');

        return is_string($connection)
            && $this->queueConnectionIsPersistent($connection);
    }

    /**
     * @param  list<string>  $visited
     */
    private function queueConnectionIsPersistent(string $connection, array $visited = []): bool
    {
        if (in_array($connection, $visited, true)) {
            return false;
        }

        $configuration = config("queue.connections.{$connection}");

        if (! is_array($configuration)) {
            return false;
        }

        $driver = $configuration['driver'] ?? null;

        if ($driver === 'failover') {
            $connections = $configuration['connections'] ?? null;

            if (! is_array($connections) || $connections === []) {
                return false;
            }

            $visited[] = $connection;

            foreach ($connections as $fallbackConnection) {
                if (! is_string($fallbackConnection)
                    || ! $this->queueConnectionIsPersistent($fallbackConnection, $visited)) {
                    return false;
                }
            }

            return true;
        }

        return is_string($driver)
            && in_array($driver, ['database', 'redis', 'sqs', 'beanstalkd'], true);
    }

    private function usesDeliveryCapableMailer(): bool
    {
        $mailer = config('mail.default');

        return is_string($mailer)
            && $this->mailerCanDeliver($mailer);
    }

    /**
     * @param  list<string>  $visited
     */
    private function mailerCanDeliver(string $mailer, array $visited = []): bool
    {
        if (in_array($mailer, $visited, true)) {
            return false;
        }

        $configuration = config("mail.mailers.{$mailer}");

        if (! is_array($configuration)) {
            return false;
        }

        $transport = $configuration['transport'] ?? null;

        if (in_array($transport, ['failover', 'roundrobin'], true)) {
            $mailers = $configuration['mailers'] ?? null;

            if (! is_array($mailers) || $mailers === []) {
                return false;
            }

            $visited[] = $mailer;

            foreach ($mailers as $memberMailer) {
                if (! is_string($memberMailer)
                    || ! $this->mailerCanDeliver($memberMailer, $visited)) {
                    return false;
                }
            }

            return true;
        }

        return is_string($transport)
            && in_array($transport, [
                'smtp',
                'sendmail',
                'mailgun',
                'ses',
                'ses-v2',
                'postmark',
                'resend',
            ], true);
    }

    private function runtimeDirectoriesAreWritable(): bool
    {
        $runtimeDirectories = [
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ];

        foreach ($runtimeDirectories as $directory) {
            if (! is_dir($directory) || ! is_writable($directory)) {
                return false;
            }
        }

        return true;
    }
}
