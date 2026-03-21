<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class SpendingByCategoryChart extends ApexChartWidget
{
    protected static ?string $chartId = 'spendingByCategoryChart';

    protected static ?string $heading = 'Spending by Category';

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 1;

    protected function getOptions(): array
    {
        $data = Transaction::forPeriod(now()->year, now()->month)
            ->where('type', 'expense')
            ->with('category.parent')
            ->get()
            ->groupBy(fn (Transaction $t) => $t->category?->parent?->name ?? $t->category?->name ?? 'Uncategorised')
            ->map(fn ($group) => round($group->sum('amount') / 100, 2));

        return [
            'chart' => [
                'type'   => 'donut',
                'height' => 320,
            ],
            'series' => $data->values()->toArray(),
            'labels' => $data->keys()->toArray(),
            'legend' => [
                'position' => 'bottom',
            ],
            'tooltip' => [
                'y' => [
                    'formatter' => "function(v){ return '€' + parseFloat(v).toFixed(2) }",
                ],
            ],
            'dataLabels' => [
                'enabled' => true,
                'formatter' => "function(val){ return val.toFixed(1) + '%' }",
            ],
            'plotOptions' => [
                'pie' => [
                    'donut' => [
                        'size' => '65%',
                    ],
                ],
            ],
        ];
    }
}
