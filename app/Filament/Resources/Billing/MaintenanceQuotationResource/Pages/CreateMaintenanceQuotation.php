<?php

namespace App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Filament\Resources\Billing\MaintenanceQuotationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMaintenanceQuotation extends CreateRecord
{
    protected static string $resource = MaintenanceQuotationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $record = parent::handleRecordCreation($data);

        if ($record->maintenanceRequest) {
            $record->maintenanceRequest->update([
                'current_client_quote_id' => $record->id,
                'quotation_amount' => $record->total_amount,
                'quotation_status' => $record->status ?? 'pending',
                'status' => MaintenanceStatus::QUOTATION_PENDING,
            ]);
            $record->maintenanceRequest->syncQuotationTotals();
        }

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
