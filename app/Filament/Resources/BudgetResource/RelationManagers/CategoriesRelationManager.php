<?php

namespace App\Filament\Resources\BudgetResource\RelationManagers;

use App\Models\Category;
use Filament\Actions\AssociateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DissociateAction;
use Filament\Actions\DissociateBulkAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesRelationManager extends RelationManager
{
    protected static string $relationship = 'categories';

    protected static ?string $title = 'Assigned Categories';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->modifyQueryUsing(fn ($query) => $query->with('parent')->orderByRaw('COALESCE(parent_id, id)')->orderByRaw('parent_id IS NOT NULL')->orderBy('name'))
            ->columns([
                TextColumn::make('icon')
                    ->label('')
                    ->icon(fn (Category $record): string => $record->icon ?? 'heroicon-o-tag')
                    ->color(fn (Category $record): string => $record->parent_id ? 'gray' : 'primary'),

                TextColumn::make('name')
                    ->state(fn (Category $record): string => $record->parent
                        ? $record->parent->name . ' › ' . $record->name
                        : $record->name
                    )
                    ->weight(fn (Category $record): string => $record->parent_id ? 'normal' : 'bold')
                    ->searchable(),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn ($state): string => match ($state->value) {
                        'income'  => 'success',
                        'expense' => 'danger',
                        default   => 'gray',
                    }),
            ])
            ->headerActions([
                AssociateAction::make()
                    ->label('Add Category')
                    ->recordSelectOptionsQuery(fn ($query) => $query
                        ->with('parent')
                        ->orderByRaw('COALESCE(parent_id, id)')
                        ->orderByRaw('parent_id IS NOT NULL')
                        ->orderBy('name')
                    )
                    ->recordTitle(fn (Category $record): string => $record->parent
                        ? $record->parent->name . ' › ' . $record->name
                        : $record->name
                    ),
            ])
            ->recordActions([
                DissociateAction::make()
                    ->label('Remove')
                    ->icon('heroicon-o-x-mark'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DissociateBulkAction::make()->label('Remove selected'),
                ]),
            ]);
    }
}
