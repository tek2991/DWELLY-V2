<?php

namespace App\Filament\Resources\Property\InventoryTypes\Pages;

use App\Filament\Resources\Property\InventoryTypes\InventoryTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryType extends EditRecord
{
    protected static string $resource = InventoryTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
