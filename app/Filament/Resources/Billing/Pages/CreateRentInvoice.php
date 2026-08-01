<?php

namespace App\Filament\Resources\Billing\Pages;

use App\Filament\Resources\Billing\RentInvoicesResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRentInvoice extends CreateRecord
{
    protected static string $resource = RentInvoicesResource::class;
}
