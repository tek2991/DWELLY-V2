<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\Pages;

use Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\DebitNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDebitNotes extends ListRecords
{
    protected static string $resource = DebitNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
