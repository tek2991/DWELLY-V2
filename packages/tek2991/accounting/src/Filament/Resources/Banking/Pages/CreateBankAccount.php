<?php

namespace Tek2991\Accounting\Filament\Resources\Banking\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tek2991\Accounting\Filament\Resources\Banking\BankAccountResource;

class CreateBankAccount extends CreateRecord
{
    protected static string $resource = BankAccountResource::class;
}
