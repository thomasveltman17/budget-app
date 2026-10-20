<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Trips\TripResource;
use App\Models\Trip;
use App\Services\TripService;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ActiveTripWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 0;

    /**
     * Only worth dashboard space while a trip is running or still being settled.
     */
    public static function canView(): bool
    {
        return Trip::unsettled()->exists();
    }

    protected function getStats(): array
    {
        $trip = Trip::unsettled()->orderByDesc('start_date')->first();

        if ($trip === null) {
            return [];
        }

        $service = app(TripService::class);
        $summary = $service->summary($trip);
        $outstanding = $summary['outstanding'];
        $budget = $summary['budget'];

        $outstandingStat = match (true) {
            $outstanding === 0 => Stat::make('Settled up', 'All square')
                ->icon('heroicon-o-check-circle')
                ->description('Nothing left to settle')
                ->color('success'),
            $outstanding > 0 => Stat::make('The group owes you', $this->money($outstanding))
                ->icon('heroicon-o-arrow-down-left')
                ->description('You fronted more than your share')
                ->color('warning'),
            default => Stat::make('You owe the group', $this->money(abs($outstanding)))
                ->icon('heroicon-o-arrow-up-right')
                ->description('Others fronted part of your share')
                ->color('danger'),
        };

        return [
            Stat::make($trip->name, $this->money($summary['my_share']))
                ->icon('heroicon-o-map')
                ->description($budget['percentage'] !== null
                    ? $budget['percentage'].'% of your '.$this->money($budget['budget']).' budget'
                    : $this->money($summary['per_day']).' per day')
                ->descriptionColor($budget['is_over'] ? 'danger' : 'gray')
                ->color('primary')
                ->url(TripResource::getUrl('view', ['record' => $trip])),

            Stat::make('Out of pocket', $this->money($summary['out_of_pocket']))
                ->icon('heroicon-o-credit-card')
                ->description('Cash that left your account')
                ->color('gray'),

            $outstandingStat,
        ];
    }

    private function money(int $cents): string
    {
        return '€'.number_format($cents / 100, 2);
    }
}
