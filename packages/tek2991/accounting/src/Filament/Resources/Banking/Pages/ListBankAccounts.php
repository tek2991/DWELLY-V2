<?php

namespace Tek2991\Accounting\Filament\Resources\Banking\Pages;

use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Tek2991\Accounting\Filament\Resources\Banking\BankAccountResource;

class ListBankAccounts extends ListRecords
{
    protected static string $resource = BankAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
