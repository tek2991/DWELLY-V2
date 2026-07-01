<?php

namespace App\Filament\Resources\CommunicationLogs\Pages;

use App\Filament\Resources\CommunicationLogs\CommunicationLogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCommunicationLog extends EditRecord
{
    protected static string $resource = CommunicationLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
