<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccessLifecycleService
{
    /**
     * @return array{token_count: int, session_count: int}
     */
    public function revokeUserAccess(User $user): array
    {
        $tokenCount = $this->revokeTokens($user);
        $sessionCount = $this->revokeSessions($user);

        $user->forceFill([
            'remember_token' => Str::random(60),
        ])->saveQuietly();

        return [
            'token_count' => $tokenCount,
            'session_count' => $sessionCount,
        ];
    }

    public function revokeTokens(User $user): int
    {
        $tokenCount = $user->tokens()->count();

        if ($tokenCount > 0) {
            $user->tokens()->delete();
        }

        return $tokenCount;
    }

    public function revokeSessions(User $user): int
    {
        $sessionTable = (string) config('session.table', 'sessions');

        $sessionCount = DB::table($sessionTable)
            ->where('user_id', $user->getKey())
            ->count();

        if ($sessionCount > 0) {
            DB::table($sessionTable)
                ->where('user_id', $user->getKey())
                ->delete();
        }

        return $sessionCount;
    }
}
