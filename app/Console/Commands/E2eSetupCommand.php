<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class E2eSetupCommand extends Command
{
    protected $signature = 'e2e:setup {--fresh : Drop all tables before running migrations}';

    protected $description = 'Set up the E2E test database with fresh migrations and seed data';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->components->error('The E2E database setup command cannot run in production.');

            return Command::FAILURE;
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
            $this->components->error('The E2E setup command requires the dedicated database/e2e.sqlite connection.');

            return Command::FAILURE;
        }

        if ($this->option('fresh')) {
            $this->call('migrate:fresh', [
                '--force' => true,
                '--seed' => false,
            ]);
        } else {
            $this->call('migrate', ['--force' => true]);
        }

        $this->call('db:seed', [
            '--class' => 'Database\Seeders\E2eSeeder',
            '--force' => true,
        ]);

        $this->info('E2E database setup complete.');

        return Command::SUCCESS;
    }
}
