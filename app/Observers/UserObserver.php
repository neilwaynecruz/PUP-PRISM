<?php

namespace App\Observers;

use App\Models\User;
use App\Services\AuditLogService;

class UserObserver
{
    public function updated(User $user): void
    {
        if ($this->wasTwoFactorConfirmed($user)) {
            AuditLogService::log(
                'two_factor_enabled',
                "Two-factor authentication enabled for {$user->name}.",
                $user,
                [
                    'two_factor_confirmed_at' => null,
                ],
                [
                    'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
                ],
            );

            return;
        }

        if ($this->wasTwoFactorDisabled($user)) {
            AuditLogService::log(
                'two_factor_disabled',
                "Two-factor authentication disabled for {$user->name}.",
                $user,
                [
                    'two_factor_confirmed_at' => $user->getOriginal('two_factor_confirmed_at'),
                ],
                [
                    'two_factor_confirmed_at' => null,
                ],
            );
        }
    }

    private function wasTwoFactorConfirmed(User $user): bool
    {
        return $user->wasChanged('two_factor_confirmed_at')
            && $user->getOriginal('two_factor_confirmed_at') === null
            && $user->two_factor_confirmed_at !== null;
    }

    private function wasTwoFactorDisabled(User $user): bool
    {
        return $user->wasChanged('two_factor_confirmed_at')
            && $user->getOriginal('two_factor_confirmed_at') !== null
            && $user->two_factor_confirmed_at === null;
    }
}
