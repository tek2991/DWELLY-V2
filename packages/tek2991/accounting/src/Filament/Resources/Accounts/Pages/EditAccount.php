<?php

namespace Tek2991\Accounting\Filament\Resources\Accounts\Pages;

use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Tek2991\Accounting\Filament\Resources\Accounts\AccountResource;

class EditAccount extends EditRecord
{
    protected static string $resource = AccountResource::class;

    protected function getActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
