<?php

namespace App\Filament\Resources\Settings\FinancialModelResource\Pages;

use App\Filament\Resources\Settings\FinancialModelResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageFinancialModels extends ManageRecords
{
    protected static string $resource = FinancialModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
