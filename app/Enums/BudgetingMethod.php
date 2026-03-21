<?php

namespace App\Enums;

enum BudgetingMethod: string
{
    case FiftyThirtyTwenty = 'fifty_thirty_twenty';
    case SeventyTwentyTen  = 'seventy_twenty_ten';
    case PayYourselfFirst  = 'pay_yourself_first';
    case Custom            = 'custom';
}
