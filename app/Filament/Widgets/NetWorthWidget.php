<?php

namespace App\Filament\Widgets;

use App\Models\Account;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class NetWorthWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $netWorth = Account::where('is_active', true)->sum('balance');
        $period   = now()->format('F Y');

        $monthIncome  = Transaction::forPeriod(now()->year, now()->month)->where('type', 'income')->sum('amount');
        $monthExpense = Transaction::forPeriod(now()->year, now()->month)->where('type', 'expense')->sum('amount');
        $change       = $monthIncome - $monthExpense;

        $changeLabel = ($change >= 0 ? '+' : '') . '€' . number_format(abs($change) / 100, 2) . ' this month';

        return [
            Stat::make('Net Worth', '€' . number_format($netWorth / 100, 2))
                ->icon('heroicon-o-banknotes')
                ->description($changeLabel)
                ->descriptionIcon($change >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($change >= 0 ? 'success' : 'danger')
                ->color('primary'),

            Stat::make('Income', '€' . number_format($monthIncome / 100, 2))
                ->icon('heroicon-o-arrow-trending-up')
                ->description('For ' . $period)
                ->color('success'),

            Stat::make('Expenses', '€' . number_format($monthExpense / 100, 2))
                ->icon('heroicon-o-arrow-trending-down')
                ->description('For ' . $period)
                ->color('danger'),
        ];
    }
}
