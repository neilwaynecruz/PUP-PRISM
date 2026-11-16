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
            ->map(fn (mixed $resource) => static::make($resource)->resolve($request))
            ->values()
            ->all();
    }
}
