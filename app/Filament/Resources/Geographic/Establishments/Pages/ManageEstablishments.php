<?php

namespace App\Filament\Resources\Geographic\Establishments\Pages;

use App\Filament\Resources\Geographic\Establishments\EstablishmentResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageEstablishments extends ManageRecords
{
    protected static string $resource = EstablishmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
