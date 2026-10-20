<?php

namespace App\Console\Commands;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('e2e:configure-two-factor {email : The email address of the E2E user} {--pending : Leave setup unconfirmed}')]
#[Description('Configure two-factor authentication for a user in the isolated E2E environment')]
class E2eConfirmTwoFactorCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! app()->environment('e2e')) {
            $this->components->error('This command is restricted to the E2E environment.');

            return self::FAILURE;
        }

        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $this->components->error("User with email '{$email}' was not found.");

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => encrypt('e2e-secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['e2e-recovery-code'], JSON_THROW_ON_ERROR)),
            'two_factor_confirmed_at' => $this->option('pending') ? null : CarbonImmutable::now(),
        ])->save();

        $state = $this->option('pending') ? 'pending' : 'confirmed';
        $this->components->info("Two-factor authentication is {$state} for '{$email}'.");

        return self::SUCCESS;
    }
}
