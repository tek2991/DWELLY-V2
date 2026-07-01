<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\Pages;

use Tek2991\Accounting\Filament\Resources\Sales\CreditNotes\CreditNoteResource;
use Filament\Resources\Pages\CreateRecord;
use Tek2991\Accounting\Services\CreditNoteService;

class CreateCreditNote extends CreateRecord
{
    protected static string $resource = CreditNoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrent()?->id;
        if (!$branchId) {
            throw new \Exception('No active branch context.');
        }
        $data['branch_id'] = $branchId;
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(CreditNoteService::class);
        return $service->create($data);
    }
    
    protected function afterCreate(): void
    {
        $service = app(CreditNoteService::class);
        $this->record->load('items');
        $service->recalculateTotals($this->record);
    }
}
