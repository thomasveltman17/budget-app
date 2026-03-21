<?php

namespace App\Filament\Resources\CategoryRules\Tables;

use App\Models\Category;
use App\Models\CategoryRule;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority', 'desc')
            ->columns([
                TextColumn::make('keyword')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('match_type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state->label()),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->formatStateUsing(function ($state, CategoryRule $record): string {
                        $cat = $record->category;
                        if (! $cat) {
                            return '—';
                        }
                        return $cat->parent
                            ? $cat->parent->name . ' › ' . $cat->name
                            : $cat->name;
                    })
                    ->searchable(),

                TextColumn::make('priority')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
