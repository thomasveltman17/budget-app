<?php

namespace App\Filament\Resources\SavingsGoalResource\Pages;

use App\Filament\Resources\SavingsGoalResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSavingsGoal extends EditRecord
{
    protected static string $resource = SavingsGoalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
