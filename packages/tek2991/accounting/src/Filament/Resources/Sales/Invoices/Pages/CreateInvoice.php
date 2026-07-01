<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\Invoices\Pages;

use Tek2991\Accounting\Filament\Resources\Sales\Invoices\InvoiceResource;
use Filament\Resources\Pages\CreateRecord;
use Tek2991\Accounting\Services\InvoiceService;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

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
        $service = app(InvoiceService::class);
        $invoice = $service->create($data);
        
        // Items are created via the Filament relationship manager/repeater automatically,
        // but we need to recalculate totals after they are saved.
        return $invoice;
    }
    
    protected function afterCreate(): void
    {
        $service = app(InvoiceService::class);
        $this->record->load('items');
        $service->recalculateTotals($this->record);
    }
}
