<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Carbon;

class BudgetPeriodService
{
    public function getPeriodStart(User $user, int $year, int $month): Carbon
    {
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        $day = min($user->budget_start_day, $daysInMonth);

        return Carbon::create($year, $month, $day)->startOfDay();
    }

    public function getCurrentPeriodStart(User $user): Carbon
    {
        $today = Carbon::today();
        $thisMonthStart = $this->getPeriodStart($user, $today->year, $today->month);

        if ($today->lt($thisMonthStart)) {
            $lastMonth = $today->copy()->subMonthNoOverflow();

            return $this->getPeriodStart($user, $lastMonth->year, $lastMonth->month);
        }

        return $thisMonthStart;
    }

    public function getCurrentPeriodEnd(User $user): Carbon
    {
        $periodStart = $this->getCurrentPeriodStart($user);
        $nextMonth = $periodStart->copy()->addMonthNoOverflow();
        $nextPeriodStart = $this->getPeriodStart($user, $nextMonth->year, $nextMonth->month);

        return $nextPeriodStart->subDay()->startOfDay();
    }
}
