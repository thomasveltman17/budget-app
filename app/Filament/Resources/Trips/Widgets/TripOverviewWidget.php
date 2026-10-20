<?php

namespace App\Filament\Resources\Trips\Widgets;

use App\Models\Trip;
use App\Services\TripService;
use Filament\Widgets\Widget;

class TripOverviewWidget extends Widget
{
    public ?Trip $record = null;

    protected string $view = 'filament.widgets.trip-overview';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $service = app(TripService::class);

        $categories = $service->breakdownByCategory($this->record);
        $days = $service->breakdownByDay($this->record);

        return [
            'trip' => $this->record,
            'summary' => $service->summary($this->record),
            'categories' => $categories,
            'days' => $days,
            'peakDay' => $days->max('amount') ?: 1,
            'savedSoFar' => $this->record->savingsGoal?->current_amount,
        ];
    }
}
