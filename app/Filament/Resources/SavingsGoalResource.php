<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SavingsGoalResource\Pages;
use App\Models\SavingsGoal;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SavingsGoalResource extends Resource
{
    protected static ?string $model = SavingsGoal::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-trophy';

    protected static ?string $navigationLabel = 'Savings Goals';

    protected static string|\UnitEnum|null $navigationGroup = 'Budget';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            TextInput::make('target_amount')
                ->label('Target Amount')
                ->prefix('€')
                ->numeric()
                ->step(0.01)
                ->required()
                ->afterStateHydrated(
                    fn (TextInput $component, $state) => $component->state($state / 100)
                )
                ->dehydrateStateUsing(
                    fn ($state) => (int) round((float) $state * 100)
                ),

            TextInput::make('current_amount')
                ->label('Current Amount')
                ->prefix('€')
                ->numeric()
                ->step(0.01)
                ->default(0)
                ->afterStateHydrated(
                    fn (TextInput $component, $state) => $component->state($state / 100)
                )
                ->dehydrateStateUsing(
                    fn ($state) => (int) round((float) $state * 100)
                ),

            DatePicker::make('deadline')
                ->required(),

            Textarea::make('notes')
                ->nullable()
                ->rows(3),

            Toggle::make('is_completed')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('target_amount')
                    ->label('Target')
                    ->formatStateUsing(
                        fn (int $state): string => '€' . number_format($state / 100, 2, '.', ',')
                    )
                    ->sortable(),

                TextColumn::make('current_amount')
                    ->label('Saved')
                    ->formatStateUsing(
                        fn (int $state): string => '€' . number_format($state / 100, 2, '.', ',')
                    )
                    ->sortable(),

                TextColumn::make('progress')
                    ->getStateUsing(
                        fn (SavingsGoal $record): int => $record->target_amount > 0
                            ? min(100, (int) round($record->current_amount / $record->target_amount * 100))
                            : 0
                    )
                    ->formatStateUsing(function (int $state): string {
                        $bar = $state >= 100 ? 'bg-success-500' : 'bg-primary-500';
                        return "
                            <div class='w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2 mb-1'>
                                <div class='{$bar} h-2 rounded-full' style='width:{$state}%'></div>
                            </div>
                            <span class='text-xs text-gray-600 dark:text-gray-400'>{$state}%</span>
                        ";
                    })
                    ->html(),

                TextColumn::make('deadline')
                    ->date('d M Y')
                    ->sortable()
                    ->color(function (SavingsGoal $record): string {
                        $daysUntil = now()->startOfDay()->diffInDays(
                            $record->deadline->startOfDay(),
                            false
                        );
                        return match (true) {
                            $daysUntil < 0   => 'danger',
                            $daysUntil <= 30 => 'warning',
                            default          => 'success',
                        };
                    }),

                TextColumn::make('is_completed')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Completed' : 'In Progress')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSavingsGoals::route('/'),
            'create' => Pages\CreateSavingsGoal::route('/create'),
            'edit'   => Pages\EditSavingsGoal::route('/{record}/edit'),
        ];
    }
}
