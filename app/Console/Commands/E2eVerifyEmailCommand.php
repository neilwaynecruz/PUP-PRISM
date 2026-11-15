<?php

namespace App\Console\Commands;

use App\Console\Commands\Concerns\GuardsE2eExecution;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class E2eVerifyEmailCommand extends Command
{
    use GuardsE2eExecution;

    protected $signature = 'e2e:verify-email {email : The email address of the user to verify}';

    protected $description = 'Verify a user\'s email address for E2E testing';

    public function handle(): int
    {
        if (! $this->ensureSafeE2eExecution()) {
            return Command::FAILURE;
        }

        $email = $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->error("User with email '{$email}' not found.");

            return Command::FAILURE;
        }

        if ($user->hasVerifiedEmail()) {
            $this->warn("User '{$email}' already has a verified email.");

            return Command::SUCCESS;
        }

        $user->forceFill([
            'email_verified_at' => CarbonImmutable::now(),
        ])->save();

        $this->info("Verified email for user '{$email}'.");

        return Command::SUCCESS;
    }
}
