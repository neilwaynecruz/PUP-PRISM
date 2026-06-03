<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class E2eUserIdCommand extends Command
{
    protected $signature = 'e2e:user-id {email : The email address of the user}';

    protected $description = 'Return the user ID for an E2E test user';

    public function handle(): int
    {
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
