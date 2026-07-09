<?php

namespace App\Domain\Implementation\Services;

use App\Domain\Implementation\Models\ImplementationProject;
use Exception;

class ImplementationProjectService
{
    /**
     * Handle state transitions for an implementation project.
     *
     * @param ImplementationProject $project
     * @param string $action
     * @return ImplementationProject
     */
    public function transition(ImplementationProject $project, string $action): ImplementationProject
    {
        switch ($action) {
            case 'start':
                if ($project->status !== 'created') {
                    throw new Exception("Project must be in 'created' state to start.");
                }
                $project->status = 'in_progress';
                break;

            case 'submit':
                if ($project->status !== 'in_progress') {
                    throw new Exception("Project must be 'in_progress' to submit.");
                }
                // Need to validate all mandatory stages and deliverables
                // For simplicity in this demo, just transition.
                $project->status = 'completed'; // Normally might go to review/audit
                break;

            case 'cancel':
                $project->status = 'cancelled';
                break;
                
            default:
                throw new Exception("Invalid transition action: {$action}");
        }

        $project->save();

        return $project;
    }
}
