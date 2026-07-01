<?php

namespace App\Filament\Resources\OwnerPayouts\Pages;

use App\Filament\Resources\OwnerPayouts\OwnerPayoutResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOwnerPayouts extends ListRecords
{
    protected static string $resource = OwnerPayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
