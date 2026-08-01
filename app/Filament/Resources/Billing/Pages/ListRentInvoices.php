<?php

namespace App\Filament\Resources\Billing\Pages;

use App\Filament\Resources\Billing\RentInvoicesResource;
use Filament\Resources\Pages\ListRecords;

class ListRentInvoices extends ListRecords
{
    protected static string $resource = RentInvoicesResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
