<?php

namespace App\Http\Controllers\Api\Implementation;

use App\Http\Controllers\Controller;
use App\Domain\Implementation\Models\ImplementationTask;
use App\Domain\Implementation\Models\ImplementationTaskChecklist;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ImplementationTaskController extends Controller
{
    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'sometimes|string|in:open,in_progress,blocked,completed',
            'assignee_id' => 'sometimes|nullable|exists:users,id',
        ]);

        $task = ImplementationTask::findOrFail($id);
        $task->update($validated);

        return response()->json([
            'message' => 'Task updated successfully',
            'data' => $task
        ]);
    }

    public function toggleChecklist(Request $request, string $taskId, string $checklistId): JsonResponse
    {
        $validated = $request->validate([
            'is_completed' => 'required|boolean',
        ]);

        $checklist = ImplementationTaskChecklist::where('task_id', $taskId)
            ->findOrFail($checklistId);

        $checklist->update(['is_completed' => $validated['is_completed']]);

        // Auto-complete task logic if all mandatory checklists are done
        $task = ImplementationTask::with('checklists')->findOrFail($taskId);
        $allMandatoryDone = $task->checklists->where('is_mandatory', true)->every(fn($item) => $item->is_completed);

        if ($allMandatoryDone && $task->status !== 'completed') {
            $task->update(['status' => 'completed']);
        }

        return response()->json([
            'message' => 'Checklist toggled successfully',
            'data' => $task->fresh('checklists')
        ]);
    }
}
