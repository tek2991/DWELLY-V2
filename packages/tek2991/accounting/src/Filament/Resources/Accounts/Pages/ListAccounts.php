<?php

namespace Tek2991\Accounting\Filament\Resources\Accounts\Pages;

use Filament\Resources\Pages\ListRecords;
use Tek2991\Accounting\Filament\Resources\Accounts\AccountResource;

class ListAccounts extends ListRecords
{
    protected static string $resource = AccountResource::class;

    protected function getActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make(),
        ];
    }
}
