<?php

namespace App\Filament\Resources;

use App\Enums\AccountType;
use App\Filament\Resources\AccountResource\Pages;
use App\Models\Account;
use App\Services\Trading212Service;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class AccountResource extends Resource
{
    protected static ?string $model = Account::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationLabel = 'Accounts';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Select::make('type')
                ->options([
                    'checking'   => 'Checking',
                    'savings'    => 'Savings',
                    'credit_card' => 'Credit Card',
                    'investment' => 'Investment',
                ])
                ->required()
                ->live(),

            TextInput::make('balance')
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

            TextInput::make('currency')
                ->default('EUR')
                ->maxLength(3)
                ->required(),

            Toggle::make('is_active')
                ->default(true),

            Select::make('broker')
                ->options([
                    'trading212' => 'Trading212',
                    'other'      => 'Other',
                ])
                ->nullable()
                ->live()
                ->hidden(fn (Get $get) => $get('type') !== 'investment'),

            TextInput::make('api_key')
                ->password()
                ->revealable()
                ->helperText('Find your API key in Trading212: Menu → Settings → API (Beta) → Generate Key')
                ->hidden(
                    fn (Get $get) => $get('type') !== 'investment' || $get('broker') !== 'trading212'
                ),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (AccountType $state): string => match ($state) {
                        AccountType::Checking   => 'info',
                        AccountType::Savings    => 'success',
                        AccountType::CreditCard => 'warning',
                        AccountType::Investment => 'primary',
                    }),

                TextColumn::make('balance')
                    ->formatStateUsing(
                        fn (int $state): string => '€' . number_format($state / 100, 2, '.', ',')
                    )
                    ->sortable(),

                TextColumn::make('currency'),

                ToggleColumn::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                Actions\Action::make('syncBalance')
                    ->label('Sync Balance')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->requiresConfirmation()
                    ->modalHeading('Sync balance from Trading212?')
                    ->modalDescription('This will fetch the current portfolio value and update the account balance.')
                    ->visible(fn (Account $record): bool => $record->type === AccountType::Investment)
                    ->action(function (Account $record): void {
                        try {
                            (new Trading212Service($record))->syncPortfolioValue($record);

                            Notification::make()
                                ->title('Balance synced successfully')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Sync failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListAccounts::route('/'),
            'create' => Pages\CreateAccount::route('/create'),
            'edit'   => Pages\EditAccount::route('/{record}/edit'),
        ];
    }
}
