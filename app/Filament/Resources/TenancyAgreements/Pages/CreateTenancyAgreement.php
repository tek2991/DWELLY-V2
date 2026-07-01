<?php

namespace App\Filament\Resources\TenancyAgreements\Pages;

use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Domain\Agreement\Actions\DraftTenancyAgreementAction;
use App\Domain\Property\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Form;

class CreateTenancyAgreement extends CreateRecord
{
    protected static string $resource = TenancyAgreementResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $property = Property::findOrFail($data['property_id']);
        
        $primaryTenantId = $data['primary_tenant_id'];
        unset($data['primary_tenant_id']);
        
        $roles = [
            [
                'party_id' => $primaryTenantId,
                'role_type' => 'Primary Tenant',
                'is_primary' => true,
            ]
        ];

        $action = app(DraftTenancyAgreementAction::class);
        return $action->execute($property, $data, $roles, auth()->user());
    }
}
