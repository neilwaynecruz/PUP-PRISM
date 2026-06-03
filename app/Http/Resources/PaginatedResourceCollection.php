<?php

namespace App\Http\Resources;

use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class PaginatedResourceCollection extends ResourceCollection
{
    public static $wrap = null;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $pagination */
        $pagination = method_exists($this->resource, 'toArray')
            ? $this->resource->toArray()
            : ['data' => []];

        $pagination['data'] = $this->collection
            ->map(fn (InertiaJsonResource $resource) => $resource->resolve($request))
            ->all();

        return $pagination;
    }
}
