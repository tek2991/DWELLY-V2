<?php

namespace App\Filament\Resources\Settings\EstablishmentTypeResource\Pages;

use App\Filament\Resources\Settings\EstablishmentTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEstablishmentTypes extends ManageRecords
{
    protected static string $resource = EstablishmentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
