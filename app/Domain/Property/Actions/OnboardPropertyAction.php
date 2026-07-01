<?php

namespace App\Domain\Property\Actions;

use App\Domain\Property\Models\Property;
use App\Domain\Workflow\Services\WorkflowEngine;
use App\Domain\Workflow\Models\WorkflowDefinition;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Domain\Shared\Services\NumberingService;

class OnboardPropertyAction
{
    public function __construct(private WorkflowEngine $workflowEngine) {}

    public function execute(array $propertyData, User $initiator): Property
    {
        return DB::transaction(function () use ($propertyData, $initiator) {
            
            // 1. Generate code using NumberingService
            if (empty($propertyData['code'])) {
                $propertyData['code'] = NumberingService::generate('property');
            }
            
            // 2. Set default initial status
            $propertyData['status'] = 'draft';

            // 3. Create the property
            $property = Property::create($propertyData);

            // 4. Initialize Utility Config defaults
            $property->utilityConfig()->create([
                'electricity_billing' => 'direct_to_tenant',
                'water_billing' => 'included_in_rent',
                'society_fee_model' => 'owner_pays',
            ]);

            // 5. Start the Workflow
            $definition = WorkflowDefinition::where('entity_type', 'Property')
                                            ->where('is_active', true)
                                            ->first();
                                            
            if ($definition) {
                $this->workflowEngine->start($definition, $property, 'draft');
            }

            return $property;
        });
    }
}
