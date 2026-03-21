<?php

namespace App\Filament\Pages;

use App\Enums\AccountType;
use App\Services\OnboardingService;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

class Onboarding extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.onboarding';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        if (auth()->user()->onboarding_completed) {
            $this->redirect(url('/admin'));

            return;
        }

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Wizard::make([
                    // ── Step 1: Welcome + Income ──
                    Step::make('Welcome')
                        ->icon('heroicon-o-sparkles')
                        ->schema([
                            Placeholder::make('welcome')
                                ->content(new HtmlString(
                                    '<div class="text-lg font-medium text-gray-700 dark:text-gray-300">'
                                    . 'Welcome! Let\'s set up your budget in a few quick steps.'
                                    . '</div>'
                                )),

                            TextInput::make('monthly_income')
                                ->label('Monthly Net Income')
                                ->prefix('€')
                                ->numeric()
                                ->step(0.01)
                                ->required()
                                ->helperText('Your take-home pay after taxes.'),

                            TextInput::make('budget_start_day')
                                ->label('Budget Start Day')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(31)
                                ->default(1)
                                ->required()
                                ->helperText('Day of the month your budget period begins (e.g. payday).'),
                        ]),

                    // ── Step 2: Budgeting Method ──
                    Step::make('Budgeting Method')
                        ->icon('heroicon-o-calculator')
                        ->schema([
                            Radio::make('budgeting_method')
                                ->label('Choose a budgeting method')
                                ->options([
                                    'fifty_thirty_twenty' => '50 / 30 / 20',
                                    'seventy_twenty_ten'  => '70 / 20 / 10',
                                    'pay_yourself_first'  => 'Pay Yourself First',
                                    'custom'              => 'Custom',
                                ])
                                ->descriptions([
                                    'fifty_thirty_twenty' => '50% Needs, 30% Wants, 20% Savings — the most popular rule of thumb.',
                                    'seventy_twenty_ten'  => '70% Expenses, 20% Savings, 10% Giving/Debt.',
                                    'pay_yourself_first'  => 'Save 20% first, then budget the rest freely.',
                                    'custom'              => 'Define your own percentage split.',
                                ])
                                ->required()
                                ->live()
                                ->columns(2),

                            Section::make('Custom Split')
                                ->visible(fn (Get $get) => $get('budgeting_method') === 'custom')
                                ->schema([
                                    TextInput::make('custom_needs_pct')
                                        ->label('Needs %')
                                        ->numeric()
                                        ->required()
                                        ->suffix('%')
                                        ->default(50),
                                    TextInput::make('custom_wants_pct')
                                        ->label('Wants %')
                                        ->numeric()
                                        ->required()
                                        ->suffix('%')
                                        ->default(30),
                                    TextInput::make('custom_savings_pct')
                                        ->label('Savings %')
                                        ->numeric()
                                        ->required()
                                        ->suffix('%')
                                        ->default(20),
                                ])
                                ->columns(3),
                        ])
                        ->afterValidation(function (Get $get) {
                            if ($get('budgeting_method') !== 'custom') {
                                return;
                            }

                            $needs   = (float) ($get('custom_needs_pct') ?? 0);
                            $wants   = (float) ($get('custom_wants_pct') ?? 0);
                            $savings = (float) ($get('custom_savings_pct') ?? 0);
                            $total   = $needs + $wants + $savings;

                            if (abs($total - 100) > 0.01) {
                                Notification::make()
                                    ->title('Percentages must add up to 100%')
                                    ->body("Currently: {$total}%")
                                    ->danger()
                                    ->send();

                                throw new Halt();
                            }
                        }),

                    // ── Step 3: Categories ──
                    Step::make('Categories')
                        ->icon('heroicon-o-tag')
                        ->schema([
                            Radio::make('category_bundle')
                                ->label('Category Template')
                                ->options([
                                    'simple'   => 'Simple (8 categories)',
                                    'detailed' => 'Detailed (with subcategories)',
                                ])
                                ->descriptions([
                                    'simple'   => 'Flat list of common categories — quick to set up.',
                                    'detailed' => 'Parent categories with subcategories for more granular tracking.',
                                ])
                                ->default('simple')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set) {
                                    // Reset selections to all options for the newly chosen bundle
                                    $options = app(OnboardingService::class)
                                        ->getCategoryOptions($get('category_bundle') ?? 'simple');
                                    $set('selected_categories', array_keys($options));
                                }),

                            CheckboxList::make('selected_categories')
                                ->label('Categories to create')
                                ->options(fn (Get $get) => app(OnboardingService::class)
                                    ->getCategoryOptions($get('category_bundle') ?? 'simple'))
                                ->default(fn (Get $get) => array_keys(app(OnboardingService::class)
                                    ->getCategoryOptions($get('category_bundle') ?? 'simple')))
                                ->columns(2),

                            Repeater::make('custom_categories')
                                ->label('Add Custom Categories')
                                ->schema([
                                    TextInput::make('name')->required(),
                                    Select::make('type')
                                        ->options(['expense' => 'Expense', 'income' => 'Income'])
                                        ->default('expense')
                                        ->required(),
                                ])
                                ->defaultItems(0)
                                ->addActionLabel('Add custom category')
                                ->columns(2),
                        ]),

                    // ── Step 4: Accounts ──
                    Step::make('Accounts')
                        ->icon('heroicon-o-building-library')
                        ->schema([
                            Placeholder::make('accounts_intro')
                                ->content(new HtmlString(
                                    '<p class="text-sm text-gray-600 dark:text-gray-400">'
                                    . 'Add at least one account to start tracking your finances.'
                                    . '</p>'
                                )),

                            Repeater::make('accounts')
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Account Name')
                                        ->required()
                                        ->placeholder('e.g. Main Checking'),

                                    Select::make('type')
                                        ->label('Account Type')
                                        ->options([
                                            AccountType::Checking->value   => 'Checking',
                                            AccountType::Savings->value    => 'Savings',
                                            AccountType::CreditCard->value => 'Credit Card',
                                            AccountType::Investment->value => 'Investment',
                                        ])
                                        ->required()
                                        ->live(),

                                    TextInput::make('balance')
                                        ->label('Current Balance')
                                        ->prefix('€')
                                        ->numeric()
                                        ->step(0.01)
                                        ->default(0),

                                    TextInput::make('api_key')
                                        ->label('Trading212 API Key')
                                        ->password()
                                        ->revealable()
                                        ->helperText('Menu → Settings → API (Beta) → Generate Key')
                                        ->visible(fn (Get $get) => $get('type') === AccountType::Investment->value),
                                ])
                                ->minItems(1)
                                ->defaultItems(1)
                                ->addActionLabel('Add another account')
                                ->itemLabel(fn (array $state): string => $state['name'] ?? 'New Account'),
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                        <x-filament::button type="submit" size="sm">
                            Complete Setup
                        </x-filament::button>
                    BLADE))),
            ]);
    }

    public function complete(): void
    {
        $data = $this->form->getState();

        app(OnboardingService::class)->complete(auth()->user(), $data);

        Notification::make()
            ->title('Welcome! Your budget is ready.')
            ->success()
            ->send();

        $this->redirect(url('/admin'));
    }

    public function getTitle(): string
    {
        return 'Set Up Your Budget';
    }
}
