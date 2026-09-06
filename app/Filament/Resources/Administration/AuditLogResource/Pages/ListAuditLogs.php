<?php

namespace App\Filament\Resources\Administration\AuditLogResource\Pages;

use App\Filament\Resources\Administration\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
