<?php

namespace App\Filament\Resources\Properties\UtilityTypeResource\Pages;

use App\Filament\Resources\Properties\UtilityTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageUtilityTypes extends ManageRecords
{
    protected static string $resource = UtilityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
