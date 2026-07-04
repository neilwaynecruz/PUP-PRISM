<?php

namespace App\Http\Controllers;

use App\Http\Requests\GlobalSearchRequest;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;

class GlobalSearchController extends Controller
{
    public function __invoke(GlobalSearchRequest $request, GlobalSearchService $search): JsonResponse
    {
        return response()->json([
            'data' => $search->search($request->user(), $request->queryTerm()),
        ]);
    }
}
