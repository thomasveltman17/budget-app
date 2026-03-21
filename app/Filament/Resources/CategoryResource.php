<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CategoryResource\Pages;
use App\Models\Budget;
use App\Models\Category;
use Filament\Actions;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationLabel = 'Categories';

    protected static string|\UnitEnum|null $navigationGroup = 'Budget';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            Select::make('type')
                ->options([
                    'income' => 'Income',
                    'expense' => 'Expense',
                ])
                ->required(),

            Select::make('parent_id')
                ->label('Parent Category')
                ->options(fn (?Category $record) => Category::whereNull('parent_id')
                    ->when($record?->exists, fn ($q) => $q->where('id', '!=', $record->id))
                    ->pluck('name', 'id')
                )
                ->nullable()
                ->searchable()
                ->placeholder('None (top-level category)'),

            ColorPicker::make('color')
                ->default('#6366f1'),

            Select::make('icon')
                ->label('Icon')
                ->options(self::getIconOptions())
                ->default('heroicon-o-tag')
                ->searchable()
                ->allowHtml()
                ->required(),

            Select::make('budget_id')
                ->label('Budget Group')
                ->options(Budget::pluck('name', 'id'))
                ->nullable()
                ->placeholder('None'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('icon')
                    ->icon(fn (string $state): string => $state)
                    ->label('Icon')
                    ->formatStateUsing(fn (): string => '')
                    ->color(fn (Category $record): string => $record->parent_id ? 'gray' : 'primary'),

                TextColumn::make('name')
                    ->state(fn (Category $record): string => $record->parent_id
                        ? '↳ '.$record->name
                        : $record->name
                    )
                    ->weight(fn (Category $record): string => $record->parent_id ? 'normal' : 'bold')
                    ->color(fn (Category $record): ?string => $record->parent_id ? 'gray' : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state): string => match ($state->value) {
                        'income' => 'success',
                        'expense' => 'danger',
                        default => 'gray',
                    }),

                ColorColumn::make('color'),

                TextColumn::make('budget.name')
                    ->label('Budget Group')
                    ->badge()
                    ->color('warning')
                    ->placeholder('—'),
            ])
            ->recordClasses(fn (Category $record): string => $record->parent_id
                ? ''
                : 'fi-main-category'
            )
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->orderByRaw('COALESCE(parent_id, id)')
                ->orderByRaw('parent_id IS NOT NULL')
                ->orderBy('name')
            )
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'income' => 'Income',
                        'expense' => 'Expense',
                    ]),

                SelectFilter::make('level')
                    ->label('Category Level')
                    ->options([
                        'parent' => 'Parent categories',
                        'sub' => 'Subcategories',
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'parent' => $query->whereNull('parent_id'),
                        'sub' => $query->whereNotNull('parent_id'),
                        default => $query,
                    }),
            ])
            ->actions([
                Actions\Action::make('addSubcategory')
                    ->label('Add Subcategory')
                    ->icon('heroicon-o-plus')
                    ->color('gray')
                    ->visible(fn (Category $record): bool => $record->isParent())
                    ->form([
                        TextInput::make('name')
                            ->required(),

                        ColorPicker::make('color')
                            ->default('#6366f1'),

                        Select::make('icon')
                            ->label('Icon')
                            ->options(self::getIconOptions())
                            ->default('heroicon-o-tag')
                            ->searchable()
                            ->allowHtml()
                            ->required(),
                    ])
                    ->action(function (array $data, Category $record): void {
                        Category::create([
                            ...$data,
                            'type' => $record->type->value,
                            'parent_id' => $record->id,
                            'budget_id' => $record->budget_id,
                        ]);

                        Notification::make()
                            ->title('Subcategory created')
                            ->success()
                            ->send();
                    }),

                Actions\EditAction::make(),
                Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }

    private static function getIconOptions(): array
    {
        $icons = [
            'heroicon-o-shopping-bag',
            'heroicon-o-shopping-cart',
            'heroicon-o-banknotes',
            'heroicon-o-credit-card',
            'heroicon-o-wallet',
            'heroicon-o-home',
            'heroicon-o-building-office',
            'heroicon-o-building-storefront',
            'heroicon-o-truck',
            'heroicon-o-heart',
            'heroicon-o-plus-circle',
            'heroicon-o-cake',
            'heroicon-o-film',
            'heroicon-o-musical-note',
            'heroicon-o-gift',
            'heroicon-o-briefcase',
            'heroicon-o-currency-euro',
            'heroicon-o-currency-dollar',
            'heroicon-o-academic-cap',
            'heroicon-o-book-open',
            'heroicon-o-globe-alt',
            'heroicon-o-device-phone-mobile',
            'heroicon-o-computer-desktop',
            'heroicon-o-tag',
            'heroicon-o-star',
            'heroicon-o-bolt',
            'heroicon-o-fire',
            'heroicon-o-arrow-trending-up',
            'heroicon-o-chart-bar',
            'heroicon-o-clock',
            'heroicon-o-scissors',
            'heroicon-o-wrench',
            'heroicon-o-paint-brush',
            'heroicon-o-face-smile',
            'heroicon-o-sun',
            'heroicon-o-moon',
        ];

        return collect($icons)
            ->mapWithKeys(function (string $icon): array {
                $label = str($icon)
                    ->after('heroicon-o-')
                    ->replace('-', ' ')
                    ->title()
                    ->toString();
                $svg = svg($icon, ['width' => '16', 'height' => '16', 'style' => 'display:inline-block;vertical-align:middle;flex-shrink:0'])->toHtml();

                return [$icon => "<span style='display:flex;align-items:center;gap:8px'>{$svg}<span>{$label}</span></span>"];
            })
            ->toArray();
    }
}
