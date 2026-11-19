<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;
use LogicException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        if (! $app->environment('testing') || $app['config']->get('database.default') !== 'sqlite' || $app['config']->get('database.connections.sqlite.database') !== ':memory:') {
            throw new LogicException('Tests require the testing environment and an in-memory SQLite default database.');
        }

        $postgresTestDatabase = $app['config']->get('database.connections.pgsql_testing.database');

        if (! is_string($postgresTestDatabase) || ! str_ends_with($postgresTestDatabase, '_test')) {
            throw new LogicException('The PostgreSQL testing database name must end with _test.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        // Laravel 13 may load .env values during test bootstrap on some platforms
        // (e.g., Windows + PHP 8.4), causing mailer to use production settings.
        // Force array mailer so tests never hit real mail services.
        if (config('mail.default') !== 'array') {
            config()->set('mail.default', 'array');
        }
    }

    public function post($uri, array $data = [], array $headers = [])
    {
        return parent::post($uri, $this->addCsrfToken($data), $headers);
    }

    public function put($uri, array $data = [], array $headers = [])
    {
        return parent::put($uri, $this->addCsrfToken($data), $headers);
    }

    public function patch($uri, array $data = [], array $headers = [])
    {
        return parent::patch($uri, $this->addCsrfToken($data), $headers);
    }

    public function delete($uri, array $data = [], array $headers = [])
    {
        return parent::delete($uri, $this->addCsrfToken($data), $headers);
    }

    private function addCsrfToken(array $data): array
    {
        if (! isset($data['_token']) && $this->app->bound('session.store')) {
            $session = $this->app->make('session.store');
            if (! $session->isStarted()) {
                $session->start();
            }
            $data['_token'] = $session->token();
        }

        return $data;
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
