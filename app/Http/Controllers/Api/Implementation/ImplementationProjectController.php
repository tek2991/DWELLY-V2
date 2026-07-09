<?php

namespace App\Http\Controllers\Api\Implementation;

use App\Http\Controllers\Controller;
use App\Domain\Implementation\Models\ImplementationProject;
use App\Domain\Implementation\Services\ImplementationProjectService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ImplementationProjectController extends Controller
{
    public function __construct(
        protected ImplementationProjectService $projectService
    ) {}

    public function show(string $id): JsonResponse
    {
        $project = ImplementationProject::with([
            'phases.stages.tasks',
            'deliverables',
            'variables',
            'approvalSteps',
            'findings'
        ])->findOrFail($id);

        return response()->json(['data' => $project]);
    }

    public function transition(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|string',
        ]);

        $project = ImplementationProject::findOrFail($id);

        try {
            $project = $this->projectService->transition($project, $validated['action']);
            return response()->json([
                'message' => 'Transition successful',
                'data' => $project
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
