<?php

namespace App\Filament\Widgets;

use App\Models\Budget;
use App\Services\BudgetPeriodService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class BudgetOverviewWidget extends StatsOverviewWidget
{
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $user          = auth()->user();
        $periodService = app(BudgetPeriodService::class);
        $periodStart   = $periodService->getCurrentPeriodStart($user);
        $periodEnd     = $periodService->getCurrentPeriodEnd($user);
        $periodLabel   = $periodStart->format('d M') . ' – ' . $periodEnd->format('d M Y');

        $budgets        = Budget::active()->with('categories')->get();
        $totalAllocated = $budgets->sum(fn (Budget $b): int => $b->allocatedAmount($user));
        $totalSpent     = $budgets->sum(fn (Budget $b): int => $b->spentInPeriod($periodStart, $periodEnd));
        $remaining      = $totalAllocated - $totalSpent;
        $pct            = $totalAllocated > 0
            ? min(100, (int) round($totalSpent / $totalAllocated * 100))
            : 0;

        return [
            Stat::make('Allocated', '€' . number_format($totalAllocated / 100, 2, '.', ','))
                ->description($periodLabel)
                ->descriptionIcon('heroicon-m-calendar')
                ->icon('heroicon-o-wallet')
                ->color('primary'),

            Stat::make('Spent', '€' . number_format($totalSpent / 100, 2, '.', ','))
                ->description($pct . '% of budget used')
                ->descriptionIcon($pct >= 80 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-arrow-trending-up')
                ->descriptionColor($pct >= 100 ? 'danger' : ($pct >= 80 ? 'warning' : 'success'))
                ->icon('heroicon-o-arrow-trending-up')
                ->color($pct >= 100 ? 'danger' : ($pct >= 80 ? 'warning' : 'success')),

            Stat::make($remaining < 0 ? 'Over Budget' : 'Remaining', '€' . number_format(abs($remaining) / 100, 2, '.', ','))
                ->description($remaining < 0 ? 'Exceeded by this amount' : 'Still available this period')
                ->descriptionIcon($remaining < 0 ? 'heroicon-m-x-circle' : 'heroicon-m-check-circle')
                ->descriptionColor($remaining < 0 ? 'danger' : 'success')
                ->icon('heroicon-o-banknotes')
                ->color($remaining < 0 ? 'danger' : 'success'),
        ];
    }
}
