<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class InertiaJsonResource extends JsonResource
{
    public static $wrap = null;

    /**
     * @param  iterable<int, mixed>  $resources
     * @return array<int, array<string, mixed>>
     */
    public static function collectionForInertia(iterable $resources, ?Request $request = null): array
    {
        $request ??= request();

        return collect($resources)
            ->map(fn (mixed $resource) => (new static($resource))->resolve($request))
            ->values()
            ->all();
    }
}
