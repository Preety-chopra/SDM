<?php

namespace App\Enums;

enum MemoCancellationType: string
{
    case Cancelled = 'cancelled';
    case AfterCounselling = 'after_counselling';
}
