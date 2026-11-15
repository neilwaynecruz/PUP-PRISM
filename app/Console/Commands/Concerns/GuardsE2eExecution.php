<?php

namespace App\Console\Commands\Concerns;

trait GuardsE2eExecution
{
    /**
     * Ensure destructive or test-only E2E commands never execute against production or non-E2E databases.
     */
    protected function ensureSafeE2eExecution(): bool
    {
        if (app()->isProduction()) {
            $this->components->error('The E2E helper command cannot run in production.');

            return false;
        }

        $connection = (string) config('database.default');
        $database = realpath((string) config("database.connections.{$connection}.database"));
        $e2eDatabase = realpath(database_path('e2e.sqlite'));

        if (
            $connection !== 'sqlite'
            || $database === false
            || $e2eDatabase === false
            || $database !== $e2eDatabase
        ) {
            $this->components->error('This E2E helper command requires the dedicated database/e2e.sqlite connection.');

            return false;
        }

        return true;
    }
}
