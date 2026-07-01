<?php

namespace Tek2991\Accounting\Filament\Resources\Contacts\Pages;

use Tek2991\Accounting\Filament\Resources\Contacts\ContactResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditContact extends EditRecord
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }
}
