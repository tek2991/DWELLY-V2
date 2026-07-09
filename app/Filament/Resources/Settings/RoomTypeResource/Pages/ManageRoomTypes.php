<?php

namespace App\Filament\Resources\Settings\RoomTypeResource\Pages;

use App\Filament\Resources\Settings\RoomTypeResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageRoomTypes extends ManageRecords
{
    protected static string $resource = RoomTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
