<?php

namespace App\Http\Resources;

class SupplierCollection extends PaginatedResourceCollection
{
    public $collects = SupplierResource::class;
}
