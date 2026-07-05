<?php

namespace App\Filament\Resources\Settings\PropertyTypeResource\Pages;

use App\Filament\Resources\Settings\PropertyTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePropertyTypes extends ManageRecords
{
    protected static string $resource = PropertyTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
