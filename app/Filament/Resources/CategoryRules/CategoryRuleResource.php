<?php

namespace App\Filament\Resources\CategoryRules;

use App\Filament\Resources\CategoryRules\Pages\CreateCategoryRule;
use App\Filament\Resources\CategoryRules\Pages\EditCategoryRule;
use App\Filament\Resources\CategoryRules\Pages\ListCategoryRules;
use App\Filament\Resources\CategoryRules\Schemas\CategoryRuleForm;
use App\Filament\Resources\CategoryRules\Tables\CategoryRulesTable;
use App\Models\CategoryRule;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CategoryRuleResource extends Resource
{
    protected static ?string $model = CategoryRule::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Category Rules';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('category.parent');
    }

    public static function form(Schema $schema): Schema
    {
        return CategoryRuleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CategoryRulesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategoryRules::route('/'),
            'create' => CreateCategoryRule::route('/create'),
            'edit' => EditCategoryRule::route('/{record}/edit'),
        ];
    }
}
