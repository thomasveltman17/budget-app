<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TransactionResource;
use App\Models\Transaction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentTransactionsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string
    {
        return 'Recent Transactions';
    }

    protected function getTableHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('viewAll')
                ->label('View all')
                ->url(TransactionResource::getUrl('index'))
                ->color('gray')
                ->link(),
        ];
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::with(['category.parent', 'account'])
                    ->latest('date')
                    ->limit(5)
            )
            ->paginated(false)
            ->searchable(false)
            ->columns([
                TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('description')
                    ->limit(40),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->formatStateUsing(function ($state, Transaction $record): string {
                        if (! $record->category) {
                            return '—';
                        }

                        return $record->category->parent
                            ? $record->category->parent->name . ' › ' . $record->category->name
                            : $record->category->name;
                    })
                    ->placeholder('—'),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state): string => match ($state->value) {
                        'income'  => 'success',
                        'expense' => 'danger',
                        default   => 'gray',
                    }),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(
                        fn (int $state): string => '€' . number_format($state / 100, 2, '.', ',')
                    )
                    ->color(fn (Transaction $record): string => match ($record->type->value) {
                        'income'  => 'success',
                        'expense' => 'danger',
                        default   => 'gray',
                    })
                    ->alignEnd(),
            ]);
    }
}
