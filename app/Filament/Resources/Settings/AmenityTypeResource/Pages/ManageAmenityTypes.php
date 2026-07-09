<?php

namespace App\Filament\Resources\Settings\AmenityTypeResource\Pages;

use App\Filament\Resources\Settings\AmenityTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageAmenityTypes extends ManageRecords
{
    protected static string $resource = AmenityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
