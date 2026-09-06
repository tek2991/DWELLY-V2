<?php

namespace App\Filament\Resources\Billing\Pages;

use App\Filament\Pages\Billing\BulkGenerateMonthlyRent;
use App\Filament\Resources\Billing\RentDemandsResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListRentDemands extends ListRecords
{
    protected static string $resource = RentDemandsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkGenerateRent')
                ->label('Bulk Generate Rent')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->url(BulkGenerateMonthlyRent::getUrl()),
        ];
    }
}
