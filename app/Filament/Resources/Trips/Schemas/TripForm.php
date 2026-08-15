<?php

namespace App\Filament\Resources\Trips\Schemas;

use App\Enums\TripStatus;
use App\Models\SavingsGoal;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TripForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Trip')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Lisbon weekend'),

                        TextInput::make('destination')
                            ->nullable()
                            ->maxLength(255)
                            ->placeholder('e.g. Lisbon, Portugal'),

                        DatePicker::make('start_date')
                            ->required()
                            ->default(today()),

                        DatePicker::make('end_date')
                            ->required()
                            ->default(today())
                            ->afterOrEqual('start_date'),

                        Select::make('status')
                            ->options(TripStatus::options())
                            ->default(TripStatus::Planned->value)
                            ->required(),

                        TextInput::make('participants')
                            ->label('People on the trip')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(255)
                            ->default(2)
                            ->required()
                            ->helperText('Including you. Used by the "Split evenly" button.'),
                    ]),

                Section::make('Budget')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('budget_amount')
                            ->label('Planned budget')
                            ->prefix('€')
                            ->numeric()
                            ->step(0.01)
                            ->nullable()
                            ->afterStateHydrated(
                                fn (TextInput $component, $state) => $component->state(
                                    $state === null ? null : $state / 100
                                )
                            )
                            ->dehydrateStateUsing(
                                fn ($state) => filled($state) ? (int) round((float) $state * 100) : null
                            ),

                        Select::make('savings_goal_id')
                            ->label('Savings goal')
                            ->options(fn () => SavingsGoal::pluck('name', 'id'))
                            ->searchable()
                            ->nullable()
                            ->helperText('Optional — track what you set aside for this trip.'),

                        Toggle::make('counts_toward_budget')
                            ->label('Count toward monthly budgets')
                            ->default(true)
                            ->helperText('Turn off to ring-fence a big trip so it does not blow up your normal category budgets.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Appearance')
                    ->columns(2)
                    ->columnSpanFull()
                    ->collapsed()
                    ->schema([
                        ColorPicker::make('color')
                            ->default('#F59E0B'),

                        TextInput::make('icon')
                            ->default('heroicon-o-map')
                            ->helperText('Heroicon name, e.g. heroicon-o-map'),

                        Textarea::make('notes')
                            ->nullable()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
