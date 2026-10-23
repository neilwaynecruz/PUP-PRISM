<?php

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Fortify\Features;

beforeEach(function () {
    $paths = productionReadinessTestPaths();

    File::ensureDirectoryExists($paths['temporary'].'/build');
    File::put($paths['temporary'].'/build/manifest.json', '{}');
    app()->usePublicPath($paths['temporary']);
});

afterEach(function () {
    $paths = productionReadinessTestPaths();

    app()->usePublicPath($paths['original']);
    File::deleteDirectory($paths['temporary']);
});

/**
 * @return array{original: string, temporary: string}
 */
function productionReadinessTestPaths(): array
{
    /** @var array{original: string, temporary: string}|null $paths */
    static $paths = null;

    if ($paths === null) {
        $paths = [
            'original' => public_path(),
            'temporary' => storage_path('framework/testing/production-readiness-'.Str::uuid()),
        ];
    }

    return $paths;
}

/**
 * @param  array<string, mixed>  $overrides
 */
function configureSecureProductionReadiness(array $overrides = []): void
{
    config(array_replace([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.cipher' => 'AES-256-CBC',
        'app.url' => 'https://prism.example.edu',
        'database.default' => 'pgsql',
        'queue.default' => 'database',
        'mail.default' => 'smtp',
        'session.encrypt' => true,
        'session.secure' => true,
        'session.http_only' => true,
        'session.same_site' => 'lax',
        'fortify.features' => [],
        'sentry.dsn' => null,
        'broadcasting.default' => 'reverb',
    ], $overrides));
}

/**
 * @return array{ready: bool, checks: list<array{key: string, label: string, status: string, message: string}>}
 */
function runProductionReadinessCheck(): array
{
    Artisan::call('app:production-readiness', ['--json' => true]);

    $result = json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);

    if (! is_array($result) || ! isset($result['ready'], $result['checks']) || ! is_array($result['checks'])) {
        throw new UnexpectedValueException('The production readiness command returned an invalid JSON structure.');
    }

    /** @var array{ready: bool, checks: list<array{key: string, label: string, status: string, message: string}>} $result */
    return $result;
}

/**
 * @param  array{ready: bool, checks: list<array{key: string, label: string, status: string, message: string}>}  $result
 */
function productionReadinessStatus(array $result, string $key): string
{
    foreach ($result['checks'] as $check) {
        if ($check['key'] === $key) {
            return $check['status'];
        }
    }

    return 'missing';
}

test('it fails safely when production blockers are present', function () {
    config([
        'app.env' => 'local',
        'app.debug' => true,
        'app.key' => null,
        'app.url' => 'http://localhost',
        'database.default' => 'sqlite',
        'queue.default' => 'sync',
        'mail.default' => 'log',
        'session.encrypt' => false,
        'session.secure' => false,
        'session.http_only' => true,
        'session.same_site' => 'lax',
        'fortify.features' => [Features::registration()],
    ]);

    $exitCode = Artisan::call('app:production-readiness', ['--json' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(Command::FAILURE)
        ->and($output)->toContain('"ready":false')
        ->and($output)->toContain('"key":"debug"')
        ->and($output)->toContain('"status":"fail"');
});

test('it passes blocking checks with a secure production configuration', function () {
    configureSecureProductionReadiness();

    $exitCode = Artisan::call('app:production-readiness', ['--json' => true]);
    $output = Artisan::output();

    expect($exitCode)->toBe(Command::SUCCESS)
        ->and($output)->toContain('"ready":true')
        ->and($output)->toContain('"status":"warning"');
});

test('it validates the configured cipher against the exact application key length', function (string $key, string $expectedStatus) {
    configureSecureProductionReadiness(['app.key' => $key]);

    $result = runProductionReadinessCheck();

    expect(productionReadinessStatus($result, 'application_key'))->toBe($expectedStatus);
})->with([
    '31-byte raw key is rejected' => [str_repeat('a', 31), 'fail'],
    '32-byte raw key is accepted' => [str_repeat('a', 32), 'pass'],
    '33-byte raw key is rejected' => [str_repeat('a', 33), 'fail'],
    'invalid base64 key is rejected' => ['base64:not-valid-***', 'fail'],
]);

test('it checks the configured database driver rather than the connection name', function () {
    config([
        'database.connections.prism_production' => ['driver' => 'pgsql'],
        'database.connections.pgsql' => ['driver' => 'sqlite'],
    ]);

    configureSecureProductionReadiness(['database.default' => 'prism_production']);
    $postgresAlias = runProductionReadinessCheck();

    configureSecureProductionReadiness(['database.default' => 'pgsql']);
    $misleadingName = runProductionReadinessCheck();

    expect(productionReadinessStatus($postgresAlias, 'database'))->toBe('pass')
        ->and(productionReadinessStatus($misleadingName, 'database'))->toBe('fail');
});

test('it rejects queue connections that are not persistent', function (string $connection) {
    configureSecureProductionReadiness(['queue.default' => $connection]);

    $result = runProductionReadinessCheck();

    expect(productionReadinessStatus($result, 'queue'))->toBe('fail');
})->with(['sync', 'null', 'deferred', 'background', 'failover', 'missing-queue']);

test('it accepts a custom queue connection backed by a persistent driver', function () {
    config(['queue.connections.prism_queue' => ['driver' => 'database']]);
    configureSecureProductionReadiness(['queue.default' => 'prism_queue']);

    $result = runProductionReadinessCheck();

    expect(productionReadinessStatus($result, 'queue'))->toBe('pass');
});

test('it rejects missing or non-delivery mail configurations', function (string $mailer) {
    configureSecureProductionReadiness(['mail.default' => $mailer]);

    $result = runProductionReadinessCheck();

    expect(productionReadinessStatus($result, 'mail'))->toBe('fail');
})->with(['array', 'log', 'failover', 'missing-mailer']);

test('it accepts a composite mailer only when every member can deliver', function () {
    configureSecureProductionReadiness(['mail.default' => 'roundrobin']);

    $result = runProductionReadinessCheck();

    expect(productionReadinessStatus($result, 'mail'))->toBe('pass');
});

test('it fails when the production asset manifest is missing', function () {
    configureSecureProductionReadiness();
    File::delete(public_path('build/manifest.json'));

    $result = runProductionReadinessCheck();

    expect(productionReadinessStatus($result, 'asset_manifest'))->toBe('fail');
});

test('its machine-readable output never exposes the application key', function () {
    $applicationKey = str_repeat('sensitive-key-', 3);
    configureSecureProductionReadiness(['app.key' => $applicationKey]);

    Artisan::call('app:production-readiness', ['--json' => true]);

    expect(Artisan::output())->not->toContain($applicationKey);
});
