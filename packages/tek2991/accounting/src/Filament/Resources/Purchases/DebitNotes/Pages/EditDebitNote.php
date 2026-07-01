<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\Pages;

use Tek2991\Accounting\Filament\Resources\Purchases\DebitNotes\DebitNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Tek2991\Accounting\Services\DebitNoteService;

class EditDebitNote extends EditRecord
{
    protected static string $resource = DebitNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn ($record) => $record->status === \Tek2991\Accounting\Enums\DebitNoteStatus::Draft),
        ];
    }
    
    protected function afterSave(): void
    {
        $service = app(DebitNoteService::class);
        $this->record->load('items');
        $service->recalculateTotals($this->record);
    }
}
