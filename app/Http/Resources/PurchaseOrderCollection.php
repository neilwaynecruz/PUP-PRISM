<?php

namespace App\Http\Resources;

class PurchaseOrderCollection extends PaginatedResourceCollection
{
    public $collects = PurchaseOrderResource::class;
}
