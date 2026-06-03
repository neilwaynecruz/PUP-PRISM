<?php

namespace App\Http\Resources;

class BookingCollection extends PaginatedResourceCollection
{
    public $collects = BookingResource::class;
}
