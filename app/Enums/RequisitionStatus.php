<?php

namespace App\Enums;

enum RequisitionStatus: string
{
    case Draft = 'Draft';
    case Submitted = 'Submitted';
    case Approved = 'Approved';
    case Issued = 'Issued';
    case Closed = 'Closed';
    case Rejected = 'Rejected';
}
