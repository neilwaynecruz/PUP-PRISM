<?php

namespace App\Http\Controllers\Api\Concerns;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

trait PaginatesApiResources
{
    protected function perPage(Request $request): int
    {
        if (! $request->has('per_page')) {
            return (int) config('api.pagination.default_per_page', 25);
        }

        $validator = validator($request->all(), [
            'per_page' => ['required', 'integer', 'min:1', 'max:'.config('api.pagination.max_per_page', 100)],
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        return (int) $validator->validated()['per_page'];
    }
}
