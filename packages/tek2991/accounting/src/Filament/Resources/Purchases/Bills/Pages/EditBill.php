<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\Bills\Pages;

use Tek2991\Accounting\Filament\Resources\Purchases\Bills\BillResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Tek2991\Accounting\Services\BillService;

class EditBill extends EditRecord
{
    protected static string $resource = BillResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make()
                ->visible(fn ($record) => $record->status === \Tek2991\Accounting\Enums\BillStatus::Draft),
        ];
    }
    
    protected function afterSave(): void
    {
        $service = app(BillService::class);
        $this->record->load('items');
        $service->recalculateTotals($this->record);
    }
}
