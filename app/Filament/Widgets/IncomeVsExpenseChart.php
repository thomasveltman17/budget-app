<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Leandrocfe\FilamentApexCharts\Widgets\ApexChartWidget;

class IncomeVsExpenseChart extends ApexChartWidget
{
    protected static ?string $chartId = 'incomeVsExpenseChart';

    protected static ?string $heading = 'Income vs Expenses';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 1;

    protected function getOptions(): array
    {
        $months = collect(range(5, 0))->map(fn ($i) => now()->subMonths($i));

        $labels  = $months->map(fn ($m) => $m->format('M Y'))->toArray();
        $income  = $months->map(fn ($m) => round(
            Transaction::forPeriod($m->year, $m->month)->where('type', 'income')->sum('amount') / 100,
            2
        ))->toArray();
        $expense = $months->map(fn ($m) => round(
            Transaction::forPeriod($m->year, $m->month)->where('type', 'expense')->sum('amount') / 100,
            2
        ))->toArray();

        return [
            'chart' => [
                'type'    => 'bar',
                'height'  => 320,
                'toolbar' => ['show' => false],
            ],
            'series' => [
                ['name' => 'Income',   'data' => $income],
                ['name' => 'Expenses', 'data' => $expense],
            ],
            'xaxis' => [
                'categories' => $labels,
                'labels' => [
                    'style' => ['colors' => '#9ca3af', 'fontWeight' => 600],
                ],
            ],
            'yaxis' => [
                'labels' => [
                    'style' => ['colors' => '#9ca3af', 'fontWeight' => 600],
                    'formatter' => "function(v){ return '€' + v.toFixed(0) }",
                ],
            ],
            'colors' => ['#22c55e', '#ef4444'],
            'tooltip' => [
                'y' => [
                    'formatter' => "function(v){ return '€' + parseFloat(v).toFixed(2) }",
                ],
            ],
            'plotOptions' => [
                'bar' => [
                    'borderRadius'       => 4,
                    'columnWidth'        => '60%',
                ],
            ],
            'dataLabels' => ['enabled' => false],
            'legend'     => ['position' => 'top'],
        ];
    }
}
