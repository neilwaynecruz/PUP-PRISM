<?php

namespace App\Http\Responses;

use App\Support\PreventClientCaching;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Symfony\Component\HttpFoundation\Response;

class LogoutResponse implements LogoutResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        return PreventClientCaching::apply(
            redirect()->route('login'),
        );
    }
}
