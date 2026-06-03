<?php

namespace App\Http\Resources;

class StockMovementCollection extends PaginatedResourceCollection
{
    public $collects = StockMovementResource::class;
}
