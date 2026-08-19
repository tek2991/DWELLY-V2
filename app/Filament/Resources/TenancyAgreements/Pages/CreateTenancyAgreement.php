<?php

namespace App\Filament\Resources\TenancyAgreements\Pages;

use App\Domain\Agreement\Actions\DraftTenancyAgreementAction;
use App\Domain\Party\Enums\BusinessRole;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTenancyAgreement extends CreateRecord
{
    protected static string $resource = TenancyAgreementResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $property = Property::findOrFail($data['property_id']);

        $primaryTenantId = null;

        if (! empty($data['create_new_tenant']) && ! empty($data['new_tenant'])) {
            $tenantData = $data['new_tenant'];

            $party = Party::create([
                'display_name' => $tenantData['display_name'],
                'phone' => $tenantData['phone'] ?? null,
                'email' => $tenantData['email'] ?? null,
                'party_type' => 'individual',
            ]);

            $party->individual()->create([
                'name' => $tenantData['display_name'],
                'parent_name' => $tenantData['parent_name'] ?? null,
                'aadhaar_number' => $tenantData['aadhaar_number'] ?? null,
                'pan_number' => $tenantData['pan_number'] ?? null,
                'voter_id' => $tenantData['voter_id'] ?? null,
            ]);

            if (! empty($tenantData['address_line_1'])) {
                $party->addresses()->create([
                    'address_line_1' => $tenantData['address_line_1'],
                    'is_primary' => true,
                ]);
            }

            if (! empty($tenantData['pan_number']) && empty($data['tenant_bank_details']['pan_number'])) {
                if (! isset($data['tenant_bank_details']) || ! is_array($data['tenant_bank_details'])) {
                    $data['tenant_bank_details'] = [];
                }
                $data['tenant_bank_details']['pan_number'] = $tenantData['pan_number'];
            }

            $party->enableRole(BusinessRole::TENANT);
            $primaryTenantId = $party->id;
        } else {
            $primaryTenantId = $data['primary_tenant_id'] ?? null;
        }

        unset($data['create_new_tenant'], $data['new_tenant'], $data['primary_tenant_id']);

        $roles = [
            [
                'party_id' => $primaryTenantId,
                'role_type' => 'Primary Tenant',
                'is_primary' => true,
            ],
        ];

        $action = app(DraftTenancyAgreementAction::class);

        return $action->execute($property, $data, $roles, auth()->user());
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
