<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Workflow\Models\WorkflowDefinition;
use App\Domain\Workflow\Models\WorkflowInstance;
use App\Domain\Workflow\Models\WorkflowTransition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class WorkflowEngine
{
    /**
     * Start a workflow for an entity.
     */
    public function start(WorkflowDefinition $definition, Model $subject, string $initialState): WorkflowInstance
    {
        return DB::transaction(function () use ($definition, $subject, $initialState) {
            $instance = new WorkflowInstance();
            $instance->workflow_definition_id = $definition->id;
            $instance->subject_type = get_class($subject);
            $instance->subject_id = $subject->id;
            $instance->current_state = $initialState;
            $instance->status = 'in_progress';
            $instance->save();

            return $instance;
        });
    }

    /**
     * Transition a workflow to a new state.
     */
    public function transition(WorkflowInstance $instance, string $newState, User $user, string $reason = null): WorkflowInstance
    {
        return DB::transaction(function () use ($instance, $newState, $user, $reason) {
            // Check if transition is valid based on schema_json logic...
            // In a full implementation, you would parse $instance->definition->schema_json
            // to verify if current_state -> newState is a valid edge.

            $oldState = $instance->current_state;
            
            // Record transition history
            $transition = new WorkflowTransition();
            $transition->workflow_instance_id = $instance->id;
            $transition->from_state = $oldState;
            $transition->to_state = $newState;
            $transition->transitioned_by = $user->id;
            $transition->reason = $reason;
            $transition->save();

            // Update instance
            $instance->current_state = $newState;
            $instance->save();

            return $instance;
        });
    }
}
