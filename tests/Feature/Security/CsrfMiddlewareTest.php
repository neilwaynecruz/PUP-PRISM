<?php

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

test('sanctum uses the supported request forgery middleware', function () {
    expect(config('sanctum.middleware.validate_csrf_token'))->toBe(PreventRequestForgery::class);
});

test('stateful request forgery middleware still validates csrf tokens', function (?string $token, bool $accepted) {
    $middleware = new class(app(), app(Encrypter::class)) extends PreventRequestForgery
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    };

    $request = Request::create('/api/receiving', 'POST', $token === null ? [] : ['_token' => $token]);
    $request->setLaravelSession(app('session')->driver());
    $request->session()->put('_token', 'expected-csrf-token');

    $handle = fn () => $middleware->handle($request, fn () => response('accepted'));

    if (! $accepted) {
        expect($handle)->toThrow(TokenMismatchException::class);

        return;
    }

    $response = $handle();
    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe('accepted');
})->with([
    'missing token' => [null, false],
    'incorrect token' => ['incorrect-csrf-token', false],
    'matching token' => ['expected-csrf-token', true],
]);
