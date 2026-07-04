<?php

namespace App\Http\Controllers;

use App\Support\PreventClientCaching;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionStatusController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $status = $request->user() === null ? Response::HTTP_UNAUTHORIZED : Response::HTTP_NO_CONTENT;

        return PreventClientCaching::apply(
            response('', $status),
        );
    }
}
