<?php

namespace App\Http\Resources;

class ProductCollection extends PaginatedResourceCollection
{
    public $collects = ProductResource::class;
}
