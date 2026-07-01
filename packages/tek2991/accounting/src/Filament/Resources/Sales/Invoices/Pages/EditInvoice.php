<?php

namespace Tek2991\Accounting\Filament\Resources\Sales\Invoices\Pages;

use Tek2991\Accounting\Filament\Resources\Sales\Invoices\InvoiceResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Tek2991\Accounting\Services\InvoiceService;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn ($record) => $record->status === \Tek2991\Accounting\Enums\InvoiceStatus::Draft),
        ];
    }
    
    protected function afterSave(): void
    {
        $service = app(InvoiceService::class);
        $this->record->load('items');
        $service->recalculateTotals($this->record);
    }
}
