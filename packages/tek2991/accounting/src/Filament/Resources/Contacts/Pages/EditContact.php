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
            Actions\DeleteAction::make()
                ->hidden(fn () => !config('accounting.contacts.allow_delete', true)),
            Actions\RestoreAction::make(),
        ];
    }
    
    protected function getSaveFormAction(): \Filament\Actions\Action
    {
        return parent::getSaveFormAction()
            ->hidden(fn () => !config('accounting.contacts.allow_update', true));
    }
}
