<?php

namespace Tek2991\Accounting\Filament\Resources\Purchases\Bills\Pages;

use Tek2991\Accounting\Filament\Resources\Purchases\Bills\BillResource;
use Filament\Resources\Pages\CreateRecord;
use Tek2991\Accounting\Services\BillService;

class CreateBill extends CreateRecord
{
    protected static string $resource = BillResource::class;

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
        $service = app(BillService::class);
        $branch = \App\Models\Branch::find($data['branch_id']);
        $bill = $service->create($branch, $data);
        return $bill;
    }
    
    protected function afterCreate(): void
    {
        $service = app(BillService::class);
        $this->record->load('items');
        $service->recalculateTotals($this->record);
    }
}
