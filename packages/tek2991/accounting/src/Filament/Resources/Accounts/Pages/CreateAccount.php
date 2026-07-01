<?php

namespace Tek2991\Accounting\Filament\Resources\Accounts\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tek2991\Accounting\Filament\Resources\Accounts\AccountResource;

class CreateAccount extends CreateRecord
{
    protected static string $resource = AccountResource::class;

    protected function getActions(): array
    {
        return [];
    }
}
