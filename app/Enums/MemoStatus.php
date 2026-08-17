<?php

namespace App\Enums;

enum MemoStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Closed = 'closed';
    case Cancelled = 'cancelled';
}
