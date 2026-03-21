<?php

namespace App\Filament\Resources\CategoryRules\Pages;

use App\Filament\Resources\CategoryRules\CategoryRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCategoryRules extends ListRecords
{
    protected static string $resource = CategoryRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
