<?php

namespace App\Domain\Implementation\Services;

use App\Domain\Implementation\Models\ImplementationProject;
use App\Domain\Implementation\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImplementationProjectFactory
{
    /**
     * Create a new Implementation Project by snapshotting a Workflow Template.
     *
     * @param Model $entity The subject of the implementation (e.g., Property)
     * @param WorkflowTemplate $template The blueprint to snapshot
     * @return ImplementationProject
     */
    public function createFromTemplate(Model $entity, WorkflowTemplate $template): ImplementationProject
    {
        return DB::transaction(function () use ($entity, $template) {
            $project = new ImplementationProject();
            $project->workflow_template_id = $template->id;
            $project->entity_type = get_class($entity);
            $project->entity_id = $entity->id;
            $project->status = 'created';
            
            if ($template->estimated_duration_days) {
                $project->target_completion_date = Carbon::now()->addDays($template->estimated_duration_days);
            }
            
            $project->save();

            // Snapshot Variables
            foreach ($template->variables as $variable) {
                $project->variables()->create([
                    'key' => $variable->key,
                    'value' => $variable->value,
                    'type' => $variable->type,
                ]);
            }

            // Maps to track IDs for relationships
            $phaseMap = [];
            $stageMap = [];
            $taskMap = [];

            // Snapshot Phases
            foreach ($template->phases as $phase) {
                $newPhase = $project->phases()->create([
                    'name' => $phase->name,
                    'order' => $phase->order,
                ]);
                $phaseMap[$phase->id] = $newPhase->id;
            }

            // Snapshot Stages
            foreach ($template->stages as $stage) {
                $newStage = $project->stages()->create([
                    'phase_id' => $stage->phase_id ? ($phaseMap[$stage->phase_id] ?? null) : null,
                    'name' => $stage->name,
                    'order' => $stage->order,
                    'is_mandatory' => $stage->is_mandatory,
                    'status' => 'not_started',
                ]);
                $stageMap[$stage->id] = $newStage->id;
            }

            // Snapshot Deliverables
            // (We iterate template work packages to grab deliverables)
            foreach ($template->workPackages as $workPackage) {
                foreach ($workPackage->deliverables as $deliverable) {
                    $project->deliverables()->create([
                        'work_package_id' => $workPackage->id,
                        'name' => $deliverable->name,
                        'provider_key' => $deliverable->provider_key,
                        'validation_parameters' => $deliverable->validation_parameters,
                        'status' => 'pending',
                    ]);
                }
            }

            // Snapshot Tasks
            foreach ($template->taskTemplates as $taskTemplate) {
                $newTask = $project->tasks()->create([
                    'task_template_id' => $taskTemplate->id,
                    'stage_id' => $stageMap[$taskTemplate->stage_id],
                    'title' => $taskTemplate->title,
                    'description' => $taskTemplate->description,
                    'status' => 'open',
                    'weight' => $taskTemplate->weight,
                    // Assignee ID will be resolved later via assignment_rule
                ]);
                
                $taskMap[$taskTemplate->id] = $newTask->id;

                // Snapshot Checklists for this task
                foreach ($taskTemplate->checklists as $checklist) {
                    $newTask->checklists()->create([
                        'item_text' => $checklist->item_text,
                        'is_mandatory' => $checklist->is_mandatory,
                        'is_completed' => false,
                    ]);
                }
            }

            // Snapshot Task Dependencies
            // Now that all tasks exist in the instance, map their dependencies
            foreach ($template->taskTemplates as $taskTemplate) {
                foreach ($taskTemplate->dependencies as $dependency) {
                    if (isset($taskMap[$taskTemplate->id]) && isset($taskMap[$dependency->depends_on_task_template_id])) {
                        $project->tasks()->find($taskMap[$taskTemplate->id])->dependencies()->create([
                            'depends_on_task_id' => $taskMap[$dependency->depends_on_task_template_id],
                        ]);
                    }
                }
            }

            // Snapshot Approval Steps
            foreach ($template->approvalSteps as $approvalStep) {
                $project->approvalSteps()->create([
                    'step_order' => $approvalStep->step_order,
                    'required_role' => $approvalStep->required_role,
                    'status' => 'pending',
                ]);
            }

            return $project;
        });
    }
}
