<?php

namespace App\Filament\Resources\Operations\TenantDeboardingResource\Pages;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Services\TenancyDeboardingService;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenantDeboarding extends CreateRecord
{
    protected static string $resource = TenantDeboardingResource::class;

    protected static ?string $title = 'Initiate Tenant Deboarding & Move-Out';

    protected function handleRecordCreation(array $data): Model
    {
        $agreement = TenancyAgreement::findOrFail($data['tenancy_agreement_id']);
        $service = app(TenancyDeboardingService::class);

        $deboarding = $service->initiateDeboarding($agreement, $data, auth()->user());

        Notification::make()
            ->title('Deboarding Initiated')
            ->body("Deboarding #{$deboarding->code} recorded. Move-Out Exit Audit has been scheduled.")
            ->success()
            ->send();

        return $deboarding;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
