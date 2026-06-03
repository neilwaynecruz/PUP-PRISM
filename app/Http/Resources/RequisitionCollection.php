<?php

namespace App\Http\Resources;

class RequisitionCollection extends PaginatedResourceCollection
{
    public $collects = RequisitionResource::class;
}
