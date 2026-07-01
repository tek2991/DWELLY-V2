<?php

namespace App\Filament\Resources\CommunicationLogs\Pages;

use App\Filament\Resources\CommunicationLogs\CommunicationLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommunicationLog extends CreateRecord
{
    protected static string $resource = CommunicationLogResource::class;
}
