<?php

namespace App\Filament\Resources\OwnerPayouts\Pages;

use App\Filament\Pages\Billing\BulkGenerateOwnerPayouts;
use App\Filament\Resources\OwnerPayouts\OwnerPayoutResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListOwnerPayouts extends ListRecords
{
    protected static string $resource = OwnerPayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('bulkDisbursePayouts')
                ->label('Bulk Disburse Payouts')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->url(BulkGenerateOwnerPayouts::getUrl()),
        ];
    }
}
