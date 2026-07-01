<?php

namespace Tek2991\Accounting\Filament\Resources\Taxes\Pages;

use Tek2991\Accounting\Filament\Resources\Taxes\TaxResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTaxes extends ListRecords
{
    protected static string $resource = TaxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
