<?php

namespace App\Filament\Resources\Billing\Pages;

use App\Filament\Resources\Billing\RentDemandsResource;
use Filament\Resources\Pages\ListRecords;

class ListRentDemands extends ListRecords
{
    protected static string $resource = RentDemandsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
