<?php

namespace App\Filament\Resources\Property\InventoryTypes\Pages;

use App\Filament\Resources\Property\InventoryTypes\InventoryTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryTypes extends ListRecords
{
    protected static string $resource = InventoryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
