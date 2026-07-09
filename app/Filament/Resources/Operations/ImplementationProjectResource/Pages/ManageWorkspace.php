<?php

namespace App\Filament\Resources\Operations\ImplementationProjectResource\Pages;

use App\Domain\Implementation\Models\ImplementationProject;
use App\Domain\Implementation\Models\ImplementationTask;
use App\Domain\Implementation\Models\ImplementationTaskChecklist;
use App\Filament\Resources\Operations\ImplementationProjectResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class ManageWorkspace extends ViewRecord
{
    protected static string $resource = ImplementationProjectResource::class;

    protected string $view = 'filament.resources.implementation-project.pages.manage-workspace';

    public function getTitle(): string
    {
        return 'Implementation Workspace';
    }
    
    public function getSubheading(): ?string
    {
        return 'Project: ' . $this->record->workflowTemplate->name;
    }
    
    protected function getViewData(): array
    {
        $this->record->load([
            'phases.stages.tasks.checklists',
            'deliverables',
            'findings'
        ]);
        
        return [
            'project' => $this->record,
        ];
    }

    public function toggleChecklistItem($itemId)
    {
        $item = ImplementationTaskChecklist::find($itemId);
        if ($item) {
            $item->is_completed = !$item->is_completed;
            $item->save();
        }
    }
    
    public function startTaskAction(): Action
    {
        return Action::make('startTask')
            ->label('Start')
            ->icon('heroicon-o-play')
            ->color('primary')
            ->action(function (array $arguments) {
                $taskId = $arguments['task_id'];
                $task = ImplementationTask::find($taskId);
                if ($task && $task->status === 'open') {
                    $task->update(['status' => 'in_progress']);

                    // Also set stage to active if it was not started
                    if ($task->stage && $task->stage->status === 'not_started') {
                        $task->stage->update(['status' => 'active']);
                    }
                }
            });
    }

    public function markTaskCompleteAction(): Action
    {
        return Action::make('markTaskComplete')
            ->label('Complete')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->action(function (array $arguments) {
                $taskId = $arguments['task_id'];
                $task = ImplementationTask::with('checklists', 'stage.tasks')->find($taskId);
                
                if ($task) {
                    // Validate mandatory checklists
                    $hasPendingMandatory = $task->checklists
                        ->where('is_mandatory', true)
                        ->where('is_completed', false)
                        ->isNotEmpty();

                    if ($hasPendingMandatory) {
                        Notification::make()
                            ->title('Task cannot be completed')
                            ->body('Please complete all mandatory checklist items before marking this task as complete.')
                            ->danger()
                            ->send();
                        
                        return;
                    }

                    $task->update(['status' => 'completed']);
                    
                    // Check if stage is complete
                    if ($task->stage) {
                        $allTasksComplete = $task->stage->tasks->every(fn($t) => $t->status === 'completed');
                        if ($allTasksComplete) {
                            $task->stage->update(['status' => 'completed']);
                        }
                    }

                    Notification::make()
                        ->title('Task completed')
                        ->success()
                        ->send();
                }
            });
    }

    public function viewSignedPdfAction(): Action
    {
        return Action::make('viewSignedPdf')
            ->label('View MOU')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->size('sm')
            ->modalHeading('Signed MOU PDF')
            ->modalWidth('7xl')
            ->modalContent(function (array $arguments) {
                $mou = \App\Domain\Mou\Models\Mou::find($arguments['mou_id']);
                return view('components.pdf-viewer', [
                    'record' => $mou,
                    'mediaCollection' => 'signed_pdf'
                ]);
            })
            ->modalSubmitActionLabel('Download PDF')
            ->action(function (array $arguments) {
                $mou = \App\Domain\Mou\Models\Mou::find($arguments['mou_id']);
                if ($mou && $mou->hasMedia('signed_pdf')) {
                    return response()->download($mou->getFirstMedia('signed_pdf')->getPath(), $mou->number . '-signed.pdf');
                }
            });
    }
}
