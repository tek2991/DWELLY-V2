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
        $data['company_id'] = app(\Tek2991\Accounting\Contracts\CompanyAccessor::class)->getCurrentCompanyId() ?? throw new \Exception('No active company context.');
        return $data;
    }

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $service = app(CreditNoteService::class);
        return $service->create($data['company_id'], $data);
    }
    
    protected function afterCreate(): void
    {
        $service = app(CreditNoteService::class);
        $this->record->load('items');
        $service->recalculateTotals($this->record);
    }
}
