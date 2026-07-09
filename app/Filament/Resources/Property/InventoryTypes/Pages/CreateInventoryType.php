<?php

namespace App\Filament\Resources\Property\InventoryTypes\Pages;

use App\Filament\Resources\Property\InventoryTypes\InventoryTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateInventoryType extends CreateRecord
{
    protected static string $resource = InventoryTypeResource::class;
}
