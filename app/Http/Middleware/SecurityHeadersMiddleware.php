<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeadersMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Resource-Policy', 'same-origin');
        $response->headers->set(
            'Permissions-Policy',
            'camera=(self), microphone=(), geolocation=(), payment=()',
        );
        $response->headers->set('Content-Security-Policy', $this->buildContentSecurityPolicy());

        if (app()->environment('production') && $request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    private function buildContentSecurityPolicy(): string
    {
        $localDevOrigins = app()->isLocal()
            ? ' http://localhost:5173 http://127.0.0.1:5173'
            : '';

        $directives = [
            "default-src 'self'",
            "base-uri 'self'",
            "frame-ancestors 'none'",
            "object-src 'none'",
            "form-action 'self'",
            "script-src 'self' 'unsafe-inline' 'wasm-unsafe-eval'{$localDevOrigins}",
            "style-src 'self' 'unsafe-inline'{$localDevOrigins}",
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            "worker-src 'self' blob:",
            "connect-src 'self' ws: wss:{$localDevOrigins}",
        ];

        return implode('; ', $directives);
    }
}
