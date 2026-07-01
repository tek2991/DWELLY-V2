<?php

namespace Tek2991\Accounting\Filament\Resources\Banking\Pages;

use Filament\Resources\Pages\CreateRecord;
use Tek2991\Accounting\Filament\Resources\Banking\BankAccountResource;

class CreateBankAccount extends CreateRecord
{
    protected static string $resource = BankAccountResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrent()?->id;
        if ($branchId) {
            $data['branch_id'] = $branchId;
        }
        return $data;
    }
}
