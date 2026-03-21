<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TransactionResource\Pages;
use App\Jobs\AiCategorizeTransactionsJob;
use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use Filament\Actions;
use Filament\Actions\BulkAction;
use Filament\Tables\Grouping\Group;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static ?string $navigationLabel = 'Transactions';

    protected static string|\UnitEnum|null $navigationGroup = 'Finances';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['account', 'category.parent']);
    }

    public static function form(Schema $schema): Schema
    {
        $groupedCategories = function (): array {
            $grouped = [];
            Category::with('children')->whereNull('parent_id')->get()
                ->each(function (Category $parent) use (&$grouped): void {
                    $grouped[$parent->name] = $parent->children->isNotEmpty()
                        ? $parent->children->pluck('name', 'id')->toArray()
                        : [$parent->id => $parent->name];
                });
            return $grouped;
        };

        return $schema->components([
            DatePicker::make('date')
                ->required()
                ->default(today()),

            Select::make('type')
                ->options([
                    'income'  => 'Income',
                    'expense' => 'Expense',
                ])
                ->required(),

            Select::make('account_id')
                ->label('Account')
                ->options(fn () => Account::where('is_active', true)->pluck('name', 'id'))
                ->required()
                ->searchable(),

            Select::make('category_id')
                ->label('Category')
                ->options($groupedCategories)
                ->nullable()
                ->searchable(),

            TextInput::make('description')
                ->required()
                ->maxLength(255),

            TextInput::make('amount')
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

            Textarea::make('notes')
                ->nullable()
                ->rows(3),

            Toggle::make('is_reviewed')
                ->default(false),
        ]);
    }

    public static function table(Table $table): Table
    {
        $groupedCategories = function (): array {
            $grouped = [];
            Category::with('children')->whereNull('parent_id')->get()
                ->each(function (Category $parent) use (&$grouped): void {
                    $grouped[$parent->name] = $parent->children->isNotEmpty()
                        ? $parent->children->pluck('name', 'id')->toArray()
                        : [$parent->id => $parent->name];
                });
            return $grouped;
        };

        return $table
            ->defaultSort('date', 'desc')
            ->groups([
                Group::make('date')
                    ->date()
                    ->titlePrefixedWithLabel(false)
                    ->orderQueryUsing(fn ($query, string $direction) => $query->orderBy('date', 'desc')),
            ])
            ->defaultGroup('date')
            ->groupsInDropdownOnDesktop()
            ->columns([
                TextColumn::make('date')
                    ->date('d M Y')
                    ->sortable(),

                TextColumn::make('description')
                    ->searchable(),

                SelectColumn::make('category_id')
                    ->label('Category')
                    ->options(function (): array {
                        $options = [];
                        Category::with('children')
                            ->whereNull('parent_id')
                            ->orderBy('name')
                            ->get()
                            ->each(function (Category $parent) use (&$options): void {
                                if ($parent->children->isNotEmpty()) {
                                    foreach ($parent->children->sortBy('name') as $child) {
                                        $options[$child->id] = $parent->name . ' › ' . $child->name;
                                    }
                                } else {
                                    $options[$parent->id] = $parent->name;
                                }
                            });
                        return $options;
                    })
                    ->searchable()
                    ->placeholder('—'),

                TextColumn::make('account.name')
                    ->label('Account'),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state): string => match ($state->value) {
                        'income'  => 'success',
                        'expense' => 'danger',
                        default   => 'gray',
                    }),

                TextColumn::make('amount')
                    ->formatStateUsing(
                        fn (int $state): string => '€' . number_format($state / 100, 2, '.', ',')
                    )
                    ->color(fn (Transaction $record): string => match ($record->type->value) {
                        'income'  => 'success',
                        'expense' => 'danger',
                        default   => 'gray',
                    })
                    ->sortable(),

                IconColumn::make('ai_categorized')
                    ->label('AI')
                    ->boolean()
                    ->trueIcon('heroicon-o-cpu-chip')
                    ->falseIcon('')
                    ->trueColor('info')
                    ->tooltip('Categorized by AI'),

                ToggleColumn::make('is_reviewed')
                    ->label('Reviewed'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'income'  => 'Income',
                        'expense' => 'Expense',
                    ]),

                SelectFilter::make('account_id')
                    ->label('Account')
                    ->relationship('account', 'name'),

                SelectFilter::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),

                Filter::make('date_range')
                    ->label('Date Range')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('date', '<=', $data['until']));
                    }),

                TernaryFilter::make('is_reviewed')
                    ->label('Review Status')
                    ->placeholder('All')
                    ->trueLabel('Reviewed')
                    ->falseLabel('Unreviewed'),

                TernaryFilter::make('ai_categorized')
                    ->label('Categorization')
                    ->placeholder('All')
                    ->trueLabel('AI categorized')
                    ->falseLabel('Not AI categorized'),
            ])
            ->actions([
                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    BulkAction::make('markReviewed')
                        ->label('Mark as Reviewed')
                        ->icon('heroicon-o-check-circle')
                        ->requiresConfirmation()
                        ->action(fn (Collection $records) => $records->each->update(['is_reviewed' => true]))
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('assignCategory')
                        ->label('Assign Category')
                        ->icon('heroicon-o-tag')
                        ->form([
                            Select::make('category_id')
                                ->label('Category')
                                ->options($groupedCategories)
                                ->required()
                                ->searchable(),
                        ])
                        ->action(function (Collection $records, array $data): void {
                            $records->each->update(['category_id' => $data['category_id']]);

                            Notification::make()
                                ->title('Category assigned to ' . $records->count() . ' transaction(s)')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('applyRules')
                        ->label('Apply Category Rules')
                        ->icon('heroicon-o-sparkles')
                        ->requiresConfirmation()
                        ->modalHeading('Apply Category Rules')
                        ->modalDescription('This will auto-categorize all uncategorized transactions using your defined rules. Manually assigned categories will not be changed.')
                        ->action(function () {
                            $count = app(\App\Services\CategoryRuleService::class)->applyToAll();

                            Notification::make()
                                ->title($count > 0
                                    ? "{$count} transaction(s) automatically categorized"
                                    : 'No uncategorized transactions matched any rule')
                                ->success()
                                ->send();
                        })
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('aiCategorize')
                        ->label('AI Auto-categorize')
                        ->icon('heroicon-o-cpu-chip')
                        ->requiresConfirmation()
                        ->modalHeading('AI Auto-categorize')
                        ->modalDescription('Selected uncategorized transactions will be categorized using Google Gemini AI. Already-categorized transactions are skipped. Results appear as a notification when done.')
                        ->action(function (Collection $records): void {
                            $ids = $records->whereNull('category_id')->pluck('id')->toArray();

                            if (empty($ids)) {
                                Notification::make()
                                    ->title('All selected transactions are already categorized')
                                    ->warning()
                                    ->send();

                                return;
                            }

                            AiCategorizeTransactionsJob::dispatch($ids);

                            Notification::make()
                                ->title('AI categorization queued for ' . count($ids) . ' transaction(s)')
                                ->body('You will receive a notification when it completes.')
                                ->success()
                                ->send();
                        })
                        ->visible(fn (): bool => filled(auth()->user()?->google_ai_api_key) || filled(config('services.google_ai.api_key')))
                        ->deselectRecordsAfterCompletion(),

                    Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListTransactions::route('/'),
            'create' => Pages\CreateTransaction::route('/create'),
            'edit'   => Pages\EditTransaction::route('/{record}/edit'),
        ];
    }
}
