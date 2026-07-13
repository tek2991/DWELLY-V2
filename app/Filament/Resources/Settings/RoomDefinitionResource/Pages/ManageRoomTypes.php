<?php

namespace App\Filament\Resources\Settings\RoomDefinitionResource\Pages;

use App\Filament\Resources\Settings\RoomDefinitionResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRoomDefinitions extends ManageRecords
{
    protected static string $resource = RoomDefinitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
