<?php

namespace App\Domain\Agreement\Actions;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Services\AccountingBridgeService;
use App\Domain\Workflow\Services\WorkflowEngine;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class ActivateTenancyAction
{
    public function __construct(
        private WorkflowEngine $workflowEngine,
        private AccountingBridgeService $accounting
    ) {}

    public function execute(TenancyAgreement $agreement, User $actor): TenancyAgreement
    {
        return DB::transaction(function () use ($agreement, $actor) {
            
            // 1. Update status
            $agreement->status = 'active';
            $agreement->save();

            // 2. Transition Workflow to Active
            // In a real app we'd find the WorkflowInstance and transition it
            $instance = \App\Domain\Workflow\Models\WorkflowInstance::where('subject_type', get_class($agreement))
                ->where('subject_id', $agreement->id)
                ->first();
                
            if ($instance) {
                $this->workflowEngine->transition($instance, 'active', $actor, 'Tenancy activated.');
            }

            // 3. Mark property as occupied
            $property = $agreement->property;
            $property->status = 'occupied';
            $property->save();

            // 4. Sync to Accounting (open ledger, post SD invoice)
            // Fire event or call directly (in this case, action directly triggers service for transactional integrity)
            $this->accounting->postInitialInvoices($agreement);
            
            // 5. Dispatch Event for Communications / async jobs
            event(new \App\Domain\Agreement\Events\TenancyActivated($agreement));

            return $agreement;
        });
    }
}
