<?php

namespace App\Filament\Resources\Property\AmenityTypes\Pages;

use App\Filament\Resources\Property\AmenityTypes\AmenityTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAmenityTypes extends ListRecords
{
    protected static string $resource = AmenityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
