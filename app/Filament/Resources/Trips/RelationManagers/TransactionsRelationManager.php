<?php

namespace App\Filament\Resources\Trips\RelationManagers;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\Trip;
use App\Services\TripService;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Trip transactions';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('date', 'asc')
            ->columns([
                TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('description')
                    ->searchable(),

                SelectColumn::make('category_id')
                    ->label('Category')
                    ->options(fn (): array => self::categoryOptions())
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('amount')
                    ->label('Paid')
                    ->formatStateUsing(fn (int $state): string => self::money($state))
                    ->color(fn (Transaction $record): string => $record->type->value === 'income' ? 'success' : 'danger')
                    ->sortable(),

                TextColumn::make('share_amount')
                    ->label('Your share')
                    ->state(fn (Transaction $record): string => $record->is_settlement
                        ? '—'
                        : self::money($record->ownShare()))
                    ->description(fn (Transaction $record): ?string => match (true) {
                        $record->is_settlement => 'settlement',
                        $record->frontedAmount() > 0 => self::money($record->frontedAmount()).' fronted for others',
                        $record->frontedAmount() < 0 => 'paid by someone else',
                        default => null,
                    })
                    ->weight('bold'),

                IconColumn::make('is_settlement')
                    ->label('Settle')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrows-right-left')
                    ->falseIcon('')
                    ->trueColor('info')
                    ->tooltip('Settlement transfer — excluded from category spending'),
            ])
            ->headerActions([
                Actions\Action::make('pullIn')
                    ->label('Pull in by date')
                    ->icon('heroicon-o-arrow-down-on-square')
                    ->requiresConfirmation()
                    ->modalDescription('Tag every untagged transaction in this trip\'s date range to the trip.')
                    ->action(function (): void {
                        /** @var Trip $trip */
                        $trip = $this->getOwnerRecord();
                        $count = app(TripService::class)->autoAssignByDateRange($trip);

                        Notification::make()
                            ->title($count > 0
                                ? "{$count} transaction(s) added"
                                : 'No untagged transactions found in that date range')
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Actions\Action::make('setShare')
                    ->label('Set share')
                    ->icon('heroicon-o-scissors')
                    ->schema([
                        TextInput::make('share_amount')
                            ->label('Your share')
                            ->prefix('€')
                            ->numeric()
                            ->step(0.01)
                            ->nullable()
                            ->helperText('Leave empty when the whole amount is yours.')
                            ->afterStateHydrated(
                                fn (TextInput $component, $state) => $component->state(
                                    $state === null ? null : $state / 100
                                )
                            ),
                    ])
                    ->fillForm(fn (Transaction $record): array => [
                        'share_amount' => $record->share_amount,
                    ])
                    ->action(function (Transaction $record, array $data): void {
                        $record->update([
                            'share_amount' => filled($data['share_amount'])
                                ? (int) round((float) $data['share_amount'] * 100)
                                : null,
                        ]);
                    }),

                Actions\Action::make('splitEvenly')
                    ->label('Split evenly')
                    ->icon('heroicon-o-user-group')
                    ->color('gray')
                    ->action(function (Transaction $record): void {
                        /** @var Trip $trip */
                        $trip = $this->getOwnerRecord();

                        $record->update(['share_amount' => $trip->evenShareOf($record->amount)]);
                    })
                    ->visible(fn (): bool => $this->getOwnerRecord()->participants > 1),

                Actions\Action::make('removeFromTrip')
                    ->label('Remove from trip')
                    ->icon('heroicon-o-x-mark')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('The transaction stays in your ledger, it just leaves this trip.')
                    ->action(fn (Transaction $record) => $record->update(['trip_id' => null])),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    BulkAction::make('splitEvenly')
                        ->label('Split evenly')
                        ->icon('heroicon-o-user-group')
                        ->requiresConfirmation()
                        ->modalHeading('Split evenly')
                        ->modalDescription(fn (): string => 'Set your share to the amount divided by '
                            .$this->getOwnerRecord()->participants.' people on every selected transaction.')
                        ->action(function (Collection $records): void {
                            /** @var Trip $trip */
                            $trip = $this->getOwnerRecord();
                            $count = app(TripService::class)->splitEvenly($trip, $records);

                            Notification::make()
                                ->title("Split {$count} transaction(s) across {$trip->participants} people")
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('markSettlement')
                        ->label('Mark as settlement')
                        ->icon('heroicon-o-arrows-right-left')
                        ->requiresConfirmation()
                        ->modalDescription('Settlement transfers are the Tricount payouts. They count toward what you are out of pocket but never toward category spending.')
                        ->action(function (Collection $records): void {
                            $records->each->update(['is_settlement' => true, 'share_amount' => null]);

                            Notification::make()
                                ->title($records->count().' transaction(s) marked as settlement')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('removeFromTrip')
                        ->label('Remove from trip')
                        ->icon('heroicon-o-x-mark')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (Collection $records): void {
                            $records->each->update(['trip_id' => null]);

                            Notification::make()
                                ->title($records->count().' transaction(s) removed from this trip')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function categoryOptions(): array
    {
        $options = [];

        Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get()
            ->each(function (Category $parent) use (&$options): void {
                if ($parent->children->isNotEmpty()) {
                    foreach ($parent->children->sortBy('name') as $child) {
                        $options[$child->id] = $parent->name.' › '.$child->name;
                    }
                } else {
                    $options[$parent->id] = $parent->name;
                }
            });

        return $options;
    }

    private static function money(int $cents): string
    {
        return '€'.number_format($cents / 100, 2, '.', ',');
    }
}
