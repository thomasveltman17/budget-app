<?php

namespace App\Filament\Resources\CategoryRules\Schemas;

use App\Enums\MatchType;
use App\Models\Category;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CategoryRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        $flatCategories = function (): array {
            $flat = [];
            Category::with('children')->whereNull('parent_id')->get()
                ->each(function (Category $parent) use (&$flat): void {
                    if ($parent->children->isNotEmpty()) {
                        foreach ($parent->children as $child) {
                            $flat[$child->id] = $parent->name . ' › ' . $child->name;
                        }
                    } else {
                        $flat[$parent->id] = $parent->name;
                    }
                });
            return $flat;
        };

        return $schema
            ->components([
                TextInput::make('keyword')
                    ->required()
                    ->placeholder('e.g. Sodexo')
                    ->helperText('Case-insensitive. Matched against the transaction description.'),

                Select::make('match_type')
                    ->options(collect(MatchType::cases())->mapWithKeys(
                        fn (MatchType $case) => [$case->value => $case->label()]
                    ))
                    ->default(MatchType::Contains->value)
                    ->required(),

                Select::make('category_id')
                    ->label('Category')
                    ->options($flatCategories)
                    ->searchable()
                    ->required(),

                TextInput::make('priority')
                    ->numeric()
                    ->default(0)
                    ->helperText('Higher priority rules are checked first. Use this to resolve conflicts.'),
            ]);
    }
}
