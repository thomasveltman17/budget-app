<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BudgetResource\Pages;
use App\Filament\Resources\BudgetResource\RelationManagers;
use App\Models\Budget;
use App\Services\BudgetPeriodService;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BudgetResource extends Resource
{
    protected static ?string $model = Budget::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationLabel = 'Budgets';

    protected static string|\UnitEnum|null $navigationGroup = 'Budget';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('categories.parent');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(100)
                ->placeholder('e.g. Daily Expenses'),

            TextInput::make('percentage')
                ->label('Budget Percentage')
                ->suffix('%')
                ->numeric()
                ->minValue(0)
                ->maxValue(100)
                ->required()
                ->helperText('Percentage of your monthly income allocated to this group.'),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        $user          = auth()->user();
        $periodService = app(BudgetPeriodService::class);
        $periodStart   = $periodService->getCurrentPeriodStart($user);
        $periodEnd     = $periodService->getCurrentPeriodEnd($user);

        return $table
            ->columns([
                TextColumn::make('name')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('percentage')
                    ->label('%')
                    ->formatStateUsing(fn (int $state): string => "{$state}%")
                    ->sortable(),

                TextColumn::make('allocated')
                    ->label('Allocated')
                    ->getStateUsing(
                        fn (Budget $record): string =>
                            '€' . number_format($record->allocatedAmount($user) / 100, 2, '.', ',')
                    ),

                TextColumn::make('spent')
                    ->label('Spent')
                    ->getStateUsing(
                        fn (Budget $record): string =>
                            '€' . number_format($record->spentInPeriod($periodStart, $periodEnd) / 100, 2, '.', ',')
                    ),

                TextColumn::make('remaining')
                    ->getStateUsing(function (Budget $record) use ($user, $periodStart, $periodEnd): string {
                        $remaining = $record->allocatedAmount($user) - $record->spentInPeriod($periodStart, $periodEnd);
                        return '€' . number_format($remaining / 100, 2, '.', ',');
                    })
                    ->color(function (Budget $record) use ($user, $periodStart, $periodEnd): string {
                        $remaining = $record->allocatedAmount($user) - $record->spentInPeriod($periodStart, $periodEnd);
                        return $remaining >= 0 ? 'success' : 'danger';
                    }),

                TextColumn::make('usage_pct')
                    ->label('Usage')
                    ->getStateUsing(function (Budget $record) use ($user, $periodStart, $periodEnd): int {
                        $allocated = $record->allocatedAmount($user);
                        if ($allocated <= 0) {
                            return 0;
                        }
                        return min(100, (int) round(($record->spentInPeriod($periodStart, $periodEnd) / $allocated) * 100));
                    })
                    ->formatStateUsing(function (int $state): string {
                        $bar = match (true) {
                            $state >= 100 => 'bg-danger-500',
                            $state >= 80  => 'bg-warning-500',
                            default       => 'bg-success-500',
                        };

                        return "
                            <div class='w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-1'>
                                <div class='{$bar} h-2 rounded-full' style='width:{$state}%'></div>
                            </div>
                            <span class='text-xs text-gray-600 dark:text-gray-400'>{$state}%</span>
                        ";
                    })
                    ->html(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\CategoriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListBudgets::route('/'),
            'create' => Pages\CreateBudget::route('/create'),
            'edit'   => Pages\EditBudget::route('/{record}/edit'),
        ];
    }
}
