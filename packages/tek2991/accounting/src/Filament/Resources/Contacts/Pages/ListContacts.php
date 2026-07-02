<?php

namespace Tek2991\Accounting\Filament\Resources\Contacts\Pages;

use Tek2991\Accounting\Filament\Resources\Contacts\ContactResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListContacts extends ListRecords
{
    protected static string $resource = ContactResource::class;

    protected function getHeaderActions(): array
    {
        $actions = [
            Actions\CreateAction::make()
                ->hidden(fn () => !config('accounting.contacts.allow_create', true)),
        ];

        if (config('accounting.contacts.external_create_route')) {
            $actions[] = Actions\Action::make('create_external')
                ->label('New Contact')
                ->url(fn () => route(config('accounting.contacts.external_create_route')))
                ->hidden(fn () => config('accounting.contacts.allow_create', true));
        }

        return $actions;
    }
}
