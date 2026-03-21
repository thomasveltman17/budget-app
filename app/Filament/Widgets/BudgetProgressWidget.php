<?php

namespace App\Filament\Widgets;

use App\Models\Budget;
use App\Services\BudgetPeriodService;
use Filament\Widgets\Widget;

class BudgetProgressWidget extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.budget-progress';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $user          = auth()->user();
        $periodService = app(BudgetPeriodService::class);
        $periodStart   = $periodService->getCurrentPeriodStart($user);
        $periodEnd     = $periodService->getCurrentPeriodEnd($user);

        $budgets = Budget::active()
            ->with('categories')
            ->get()
            ->map(function (Budget $b) use ($user, $periodStart, $periodEnd): array {
                $allocated = $b->allocatedAmount($user);
                $spent     = $b->spentInPeriod($periodStart, $periodEnd);

                return [
                    'name'       => $b->name,
                    'percentage' => $b->percentage,
                    'amount'     => $allocated,
                    'spent'      => $spent,
                    'pct'        => $allocated > 0
                        ? min(100, (int) round($spent / $allocated * 100))
                        : 0,
                ];
            });

        return [
            'budgets'     => $budgets,
            'periodLabel' => $periodStart->format('d M') . ' – ' . $periodEnd->format('d M Y'),
        ];
    }
}
