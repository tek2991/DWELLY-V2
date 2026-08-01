<?php

namespace App\Filament\Resources\Billing\Pages;

use App\Filament\Resources\Billing\RentInvoicesResource;
use Filament\Resources\Pages\EditRecord;

class EditRentInvoice extends EditRecord
{
    protected static string $resource = RentInvoicesResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
