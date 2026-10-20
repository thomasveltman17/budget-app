<?php

namespace App\Filament\Resources\Trips\Tables;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Services\TripService;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TripsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('start_date', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Trip $record): ?string => $record->destination),

                TextColumn::make('start_date')
                    ->label('Dates')
                    ->sortable()
                    ->state(fn (Trip $record): string => $record->start_date->format('d M')
                        .' – '.$record->end_date->format('d M Y')),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (TripStatus $state): string => $state->label())
                    ->color(fn (TripStatus $state): string => $state->color()),

                TextColumn::make('my_share')
                    ->label('Cost to you')
                    ->state(fn (Trip $record): string => self::money(
                        app(TripService::class)->myShare($record)
                    ))
                    ->weight('bold'),

                TextColumn::make('out_of_pocket')
                    ->label('Out of pocket')
                    ->state(fn (Trip $record): string => self::money(
                        app(TripService::class)->outOfPocket($record)
                    ))
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('outstanding')
                    ->label('Still open')
                    ->state(function (Trip $record): string {
                        $outstanding = app(TripService::class)->outstandingBalance($record);

                        return $outstanding === 0 ? 'Settled' : self::money($outstanding);
                    })
                    ->color(fn (Trip $record): string => match (true) {
                        app(TripService::class)->outstandingBalance($record) === 0 => 'success',
                        app(TripService::class)->outstandingBalance($record) > 0 => 'warning',
                        default => 'danger',
                    })
                    ->tooltip('Positive: the group still owes you. Negative: you still owe the group.'),

                TextColumn::make('budget_amount')
                    ->label('Budget')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : self::money($state))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(TripStatus::options()),

                TernaryFilter::make('counts_toward_budget')
                    ->label('Budget treatment')
                    ->placeholder('All')
                    ->trueLabel('Counts toward budgets')
                    ->falseLabel('Ring-fenced'),
            ])
            ->actions([
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
                                ? "{$count} transaction(s) added to {$record->name}"
                                : 'No untagged transactions found in that date range')
                            ->success()
                            ->send();
                    }),

                Actions\ViewAction::make(),
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    private static function money(int $cents): string
    {
        return '€'.number_format($cents / 100, 2, '.', ',');
    }
}
