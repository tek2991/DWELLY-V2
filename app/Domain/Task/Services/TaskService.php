<?php

namespace App\Domain\Task\Services;

use App\Domain\Property\Models\Property;
use App\Domain\Shared\Services\NumberingService;
use App\Domain\Task\Enums\TaskCategory;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Models\Task;
use App\Domain\Task\Models\TaskChecklistItem;
use App\Domain\Task\Models\TaskTemplate;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    /**
     * Create a new task with automatic sequence numbering.
     */
    public function createTask(array $data, ?User $creator = null): Task
    {
        return DB::transaction(function () use ($data, $creator) {
            $taskNumber = $data['task_number'] ?? NumberingService::generate('task');

            $task = Task::create([
                'task_number' => $taskNumber,
                'branch_id' => $data['branch_id'] ?? null,
                'property_id' => $data['property_id'],
                'taskable_type' => $data['taskable_type'] ?? null,
                'taskable_id' => $data['taskable_id'] ?? null,
                'template_id' => $data['template_id'] ?? null,
                'category' => $data['category'] ?? TaskCategory::FIELD_WORK,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'priority' => $data['priority'] ?? TaskPriority::MEDIUM,
                'status' => $data['status'] ?? TaskStatus::PENDING,
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'sla_hours' => $data['sla_hours'] ?? null,
                'assigned_to_id' => $data['assigned_to_id'] ?? null,
                'created_by_id' => $creator?->id ?? auth()->id() ?? $data['created_by_id'] ?? null,
                'resolution_notes' => $data['resolution_notes'] ?? null,
                'cancellation_reason' => $data['cancellation_reason'] ?? null,
            ]);

            // If checklist items were provided as array
            if (! empty($data['checklist_items']) && is_array($data['checklist_items'])) {
                foreach ($data['checklist_items'] as $index => $itemData) {
                    if (is_string($itemData)) {
                        $itemData = ['title' => $itemData, 'is_mandatory' => true];
                    }

                    if (! empty($itemData['title'])) {
                        $task->checklistItems()->create([
                            'title' => $itemData['title'],
                            'description' => $itemData['description'] ?? null,
                            'is_mandatory' => $itemData['is_mandatory'] ?? true,
                            'is_completed' => $itemData['is_completed'] ?? false,
                            'sort_order' => $itemData['sort_order'] ?? $index,
                            'completed_at' => ! empty($itemData['is_completed']) ? now() : null,
                            'completed_by_id' => ! empty($itemData['is_completed']) ? ($creator?->id ?? auth()->id()) : null,
                        ]);
                    }
                }
            }

            return $task;
        });
    }

    /**
     * Instantiate a task from a predefined template.
     */
    public function createFromTemplate(
        TaskTemplate $template,
        Property $property,
        array $overrides = [],
        ?User $creator = null
    ): Task {
        $dueDate = null;
        if ($template->default_sla_hours) {
            $dueDate = Carbon::now()->addHours($template->default_sla_hours);
        }

        $checklistItems = $template->items->map(function ($item) {
            return [
                'title' => $item->title,
                'description' => $item->description,
                'is_mandatory' => $item->is_mandatory,
                'sort_order' => $item->sort_order,
                'is_completed' => false,
            ];
        })->toArray();

        $data = array_merge([
            'property_id' => $property->id,
            'template_id' => $template->id,
            'branch_id' => $template->branch_id,
            'category' => $template->category,
            'title' => $template->name,
            'description' => $template->description,
            'priority' => $template->default_priority,
            'status' => TaskStatus::PENDING,
            'sla_hours' => $template->default_sla_hours,
            'due_date' => $dueDate,
            'checklist_items' => $checklistItems,
        ], $overrides);

        return $this->createTask($data, $creator);
    }

    /**
     * Toggle completion of a checklist item.
     */
    public function toggleChecklistItem(TaskChecklistItem $item, bool $isCompleted, ?User $user = null): TaskChecklistItem
    {
        $item->update([
            'is_completed' => $isCompleted,
            'completed_at' => $isCompleted ? now() : null,
            'completed_by_id' => $isCompleted ? ($user?->id ?? auth()->id()) : null,
        ]);

        return $item;
    }

    /**
     * Mark a task as completed with verification guardrails.
     */
    public function completeTask(Task $task, ?string $resolutionNotes = null, ?User $user = null): Task
    {
        // Enforce mandatory checklist items completion
        $hasIncompleteMandatory = $task->checklistItems()
            ->where('is_mandatory', true)
            ->where('is_completed', false)
            ->exists();

        if ($hasIncompleteMandatory) {
            throw ValidationException::withMessages([
                'status' => 'All mandatory checklist items must be completed before marking this task as complete.',
            ]);
        }

        $task->update([
            'status' => TaskStatus::COMPLETED,
            'completed_at' => now(),
            'completed_by_id' => $user?->id ?? auth()->id(),
            'resolution_notes' => $resolutionNotes ?? $task->resolution_notes,
        ]);

        return $task;
    }

    /**
     * Cancel a task with an explicit reason.
     */
    public function cancelTask(Task $task, string $cancellationReason, ?User $user = null): Task
    {
        $task->update([
            'status' => TaskStatus::CANCELLED,
            'cancellation_reason' => $cancellationReason,
        ]);

        return $task;
    }
}
