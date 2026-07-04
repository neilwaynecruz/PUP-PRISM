<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

class PreventClientCaching
{
    /**
     * @return array<string, string>
     */
    public static function headers(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Vary' => 'Cookie',
        ];
    }

    public static function apply(Response $response): Response
    {
        foreach (self::headers() as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }
}
