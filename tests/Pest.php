<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(LazilyRefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Minimal 1x1 PNG data URI used for handover signature validation tests.
 */
function validHandoverSignaturePng(): string
{
    return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
}

function expectResponsePreventsClientCaching(TestResponse $response): void
{
    $cacheControl = (string) $response->headers->get('Cache-Control');

    expect($cacheControl)->toContain('no-store');
    expect($cacheControl)->toContain('no-cache');
    expect($cacheControl)->toContain('must-revalidate');
    expect($cacheControl)->toContain('private');
}

/**
 * Run a callback against the PostgreSQL testing database inside a rolled-back transaction.
 *
 * A query on the default connection runs first, so LazilyRefreshDatabase refreshes the
 * in-memory SQLite database and never the shared PostgreSQL testing database.
 */
function withPostgresTestingDatabase(callable $callback): void
{
    if (DB::connection()->getDriverName() === 'pgsql') {
        // When the test suite itself runs against PostgreSQL, LazilyRefreshDatabase
        // already wraps this test in a rolled-back transaction; opening a second
        // connection to the same database would deadlock against uncommitted locks.
        DB::beginTransaction();

        try {
            $callback();
        } finally {
            DB::rollBack();
        }

        return;
    }

    try {
        DB::connection('pgsql_testing')->getPdo();
    } catch (Throwable $exception) {
        test()->markTestSkipped('PostgreSQL testing connection unavailable: '.$exception->getMessage());
    }

    User::query()->exists();

    $originalConnection = config('database.default');
    config(['database.default' => 'pgsql_testing']);
    DB::connection('pgsql_testing')->beginTransaction();

    try {
        $callback();
    } finally {
        DB::connection('pgsql_testing')->rollBack();
        config(['database.default' => $originalConnection]);
    }
}
