<?php

namespace App\Domain\Agreement\Actions;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Property\Models\Property;
use App\Domain\Workflow\Services\WorkflowEngine;
use App\Domain\Workflow\Models\WorkflowDefinition;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Domain\Shared\Services\NumberingService;
use App\Domain\Finance\Services\CalculationService;

class DraftTenancyAgreementAction
{
    public function __construct(
        private WorkflowEngine $workflowEngine,
        private CalculationService $calculationService
    ) {}

    public function execute(Property $property, array $agreementData, array $tenantRoles, User $initiator): TenancyAgreement
    {
        return DB::transaction(function () use ($property, $agreementData, $tenantRoles, $initiator) {
            
            // 1. Generate code using NumberingService
            if (empty($agreementData['code'])) {
                $agreementData['code'] = NumberingService::generate('tenancy');
            }
            
            // 2. Compute/Override Security Deposit if missing using CalculationService logic (e.g. 2 months rent)
            if (empty($agreementData['security_deposit'])) {
                $agreementData['security_deposit'] = $agreementData['rent_amount'] * 2;
            }
            
            // 3. Set default initial status
            $agreementData['status'] = 'draft';
            $agreementData['property_id'] = $property->id;

            // 4. Create the Tenancy Agreement
            $agreement = TenancyAgreement::create($agreementData);

            // 5. Attach Tenants (Roles)
            foreach ($tenantRoles as $roleData) {
                $agreement->roles()->create([
                    'party_id' => $roleData['party_id'],
                    'role_type' => $roleData['role_type'], // e.g. Primary Tenant
                    'is_primary' => $roleData['is_primary'] ?? false,
                ]);
            }

            // 6. Start the Workflow
            $definition = WorkflowDefinition::where('entity_type', 'TenancyAgreement')
                                            ->where('is_active', true)
                                            ->first();
                                            
            if ($definition) {
                $this->workflowEngine->start($definition, $agreement, 'draft');
            }

            return $agreement;
        });
    }
}
