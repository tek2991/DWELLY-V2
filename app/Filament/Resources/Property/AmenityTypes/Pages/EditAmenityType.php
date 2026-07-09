<?php

namespace App\Filament\Resources\Property\AmenityTypes\Pages;

use App\Filament\Resources\Property\AmenityTypes\AmenityTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAmenityType extends EditRecord
{
    protected static string $resource = AmenityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
