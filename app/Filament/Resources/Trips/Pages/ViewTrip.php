<?php

namespace App\Filament\Resources\Trips\Pages;

use App\Filament\Resources\Trips\TripResource;
use App\Filament\Resources\Trips\Widgets\TripOverviewWidget;
use App\Models\Trip;
use App\Services\TripService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;

class ViewTrip extends ViewRecord
{
    protected static string $resource = TripResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assignTransactions')
                ->label('Pull in transactions')
                ->icon('heroicon-o-arrow-down-on-square')
                ->requiresConfirmation()
                ->modalHeading('Pull in transactions')
                ->modalDescription(fn (Trip $record): string => 'Tag every untagged transaction between '
                    .$record->start_date->format('d M Y').' and '.$record->end_date->format('d M Y')
                    .' to this trip. Transactions already on another trip are left alone.')
                ->action(function (Trip $record): void {
                    $count = app(TripService::class)->autoAssignByDateRange($record);

                    Notification::make()
                        ->title($count > 0
                            ? "{$count} transaction(s) added to this trip"
                            : 'No untagged transactions found in that date range')
                        ->success()
                        ->send();
                }),

            EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TripOverviewWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
