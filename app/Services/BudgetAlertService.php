<?php

namespace App\Services;

use App\Models\Budget;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

class BudgetAlertService
{
    public function __construct(
        private BudgetPeriodService $periodService,
    ) {}

    public function checkAndNotify(Budget $budget): void
    {
        $user      = auth()->user();
        $allocated = $budget->allocatedAmount($user);

        if ($allocated <= 0) {
            return;
        }

        $periodStart = $this->periodService->getCurrentPeriodStart($user);
        $periodEnd   = $this->periodService->getCurrentPeriodEnd($user);
        $spent       = $budget->spentInPeriod($periodStart, $periodEnd);
        $pct         = ($spent / $allocated) * 100;

        // Cache key includes period start date so alerts auto-reset each period
        $cacheKey    = "budget_alert_{$budget->id}_{$periodStart->format('Y-m-d')}";
        $lastAlerted = Cache::get($cacheKey, 0);

        if ($pct >= 100 && $lastAlerted < 100) {
            Notification::make()
                ->title("You've exceeded your {$budget->name} budget")
                ->danger()
                ->send();

            Cache::forever($cacheKey, 100);

        } elseif ($pct >= 80 && $lastAlerted < 80) {
            Notification::make()
                ->title("You've used 80% of your {$budget->name} budget")
                ->warning()
                ->send();

            Cache::forever($cacheKey, 80);
        }
    }

    public function checkAllBudgets(): void
    {
        Budget::active()
            ->with('categories')
            ->each(fn (Budget $budget) => $this->checkAndNotify($budget));
    }
}
