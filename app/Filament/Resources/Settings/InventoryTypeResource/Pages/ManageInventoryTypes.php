<?php

namespace App\Filament\Resources\Settings\InventoryTypeResource\Pages;

use App\Filament\Resources\Settings\InventoryTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageInventoryTypes extends ManageRecords
{
    protected static string $resource = InventoryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
