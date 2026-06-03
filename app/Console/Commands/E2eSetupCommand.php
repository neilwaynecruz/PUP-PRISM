<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class E2eSetupCommand extends Command
{
    protected $signature = 'e2e:setup {--fresh : Drop all tables before running migrations}';

    protected $description = 'Set up the E2E test database with fresh migrations and seed data';

    public function handle(): int
    {
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
