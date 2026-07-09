<?php

namespace App\Filament\Resources\Property\RoomTypes\Pages;

use App\Filament\Resources\Property\RoomTypes\RoomTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRoomType extends EditRecord
{
    protected static string $resource = RoomTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
