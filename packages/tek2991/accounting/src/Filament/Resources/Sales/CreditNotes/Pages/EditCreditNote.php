<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\Pages;

use Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\CreditNoteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Tek2991\Accounting\Services\CreditNoteService;

class EditCreditNote extends EditRecord
{
    protected static string $resource = CreditNoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn ($record) => $record->status === \Tek2991\Accounting\Enums\CreditNoteStatus::Draft),
        ];
    }
    
    protected function afterSave(): void
    {
        $service = app(CreditNoteService::class);
        $this->record->load('items');
        $service->recalculateTotals($this->record);
    }
}
