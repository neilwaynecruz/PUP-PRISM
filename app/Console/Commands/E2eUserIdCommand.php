<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2eExecution;
use App\Models\User;
use Illuminate\Console\Command;

class E2eUserIdCommand extends Command
{
    use GuardsE2eExecution;

    protected $signature = 'e2e:user-id {email : The email address of the user}';

    protected $description = 'Return the user ID for an E2E test user';

    public function handle(): int
    {
        if (! $this->ensureSafeE2eExecution()) {
            return Command::FAILURE;
        }

        $email = $this->argument('email');

        $userId = User::query()->where('email', $email)->value('id');

        if (! is_int($userId)) {
            $this->error("User with email '{$email}' not found.");

            return Command::FAILURE;
        }

        $this->line((string) $userId);

        return Command::SUCCESS;
    }
}
