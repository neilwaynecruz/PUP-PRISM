<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreApiTokenRequest;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('settings/ApiTokens', [
            'tokens' => $user->tokens()
                ->latest('id')
                ->get()
                ->map(fn (PersonalAccessToken $token): array => [
                    'id' => $token->id,
                    'name' => $token->name,
                    'abilities' => $this->normalizeAbilities($token->abilities),
                    'ability_labels' => $this->abilityLabels($token->abilities),
                    'last_used_at' => $token->last_used_at?->toIso8601String(),
                    'created_at' => $token->created_at?->toIso8601String(),
                    'expires_at' => $this->resolveExpiresAt($token)?->toIso8601String(),
                ])
                ->values()
                ->all(),
            'abilityOptions' => [
                ['value' => 'read', 'label' => 'Read access', 'description' => 'Allow read-only API requests.'],
                ['value' => 'write', 'label' => 'Write access', 'description' => 'Allow create and update API requests.'],
            ],
            'newToken' => $request->session()->pull('apiToken'),
        ]);
    }

    public function store(StoreApiTokenRequest $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $validated = $request->validated();

        $token = $user->createToken($validated['name'], $validated['abilities']);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('API token created. Copy it now because it will not be shown again.'),
        ]);

        return to_route('api-tokens.index')->with('apiToken', [
            'name' => $token->accessToken->name,
            'plain_text_token' => $token->plainTextToken,
            'abilities' => $this->normalizeAbilities($token->accessToken->abilities),
            'ability_labels' => $this->abilityLabels($token->accessToken->abilities),
            'expires_at' => $this->resolveExpiresAt($token->accessToken)?->toIso8601String(),
        ]);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $personalAccessToken = $user->tokens()->findOrFail($token);
        $personalAccessToken->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('API token revoked.'),
        ]);

        return to_route('api-tokens.index');
    }

    /**
     * @param  array<int, string>|null  $abilities
     * @return array<int, string>
     */
    private function normalizeAbilities(?array $abilities): array
    {
        if (! is_array($abilities) || $abilities === []) {
            return ['*'];
        }

        return array_values($abilities);
    }

    /**
     * @param  array<int, string>|null  $abilities
     * @return array<int, string>
     */
    private function abilityLabels(?array $abilities): array
    {
        return array_map(
            static fn (string $ability): string => match ($ability) {
                'read' => 'Read',
                'write' => 'Write',
                '*' => 'Full access',
                default => ucfirst($ability),
            },
            $this->normalizeAbilities($abilities),
        );
    }

    private function resolveExpiresAt(PersonalAccessToken $token): ?CarbonInterface
    {
        if ($token->expires_at !== null) {
            return $token->expires_at;
        }

        $expirationMinutes = config('sanctum.expiration');

        if (! is_numeric($expirationMinutes) || (int) $expirationMinutes <= 0) {
            return null;
        }

        return $token->created_at?->copy()->addMinutes((int) $expirationMinutes);
    }
}
