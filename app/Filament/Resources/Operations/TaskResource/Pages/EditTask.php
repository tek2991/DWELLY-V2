<?php

namespace App\Filament\Resources\Operations\TaskResource\Pages;

use App\Domain\Task\Enums\TaskStatus;
use App\Domain\Task\Services\TaskService;
use App\Filament\Resources\Operations\TaskResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class EditTask extends EditRecord
{
    protected static string $resource = TaskResource::class;

    public function getTitle(): string|Htmlable
    {
        return "Task #{$this->record->task_number} — {$this->record->title}";
    }

    public function getSubheading(): ?Htmlable
    {
        $status = $this->record->status ?? TaskStatus::PENDING;
        $statusLabel = e($status->getLabel());
        $categoryLabel = e($this->record->category?->getLabel() ?? 'General');

        $overdueBadge = '';
        if ($this->record->is_overdue) {
            $overdueBadge = '<span style="display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; background: rgba(239, 68, 68, 0.15); color: #b91c1c; border: 1px solid rgba(239, 68, 68, 0.3);">⚠️ OVERDUE</span>';
        }

        $progress = $this->record->checklist_progress;
        $progressBadge = "<span style=\"display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 9999px; font-size: 11px; font-weight: 600; background: rgba(59, 130, 246, 0.15); color: #1d4ed8; border: 1px solid rgba(59, 130, 246, 0.3);\">Checklist: {$progress}</span>";

        return new HtmlString(
            '<div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; color: #6b7280; margin-top: 0.25rem; flex-wrap: wrap;">' .
            '<span>Category: <strong style="color: inherit; font-weight: 700;">' . $categoryLabel . '</strong></span>' .
            '<span style="color: #cbd5e1;">&bull;</span>' .
            '<span>Status: <strong style="color: inherit; font-weight: 700;">' . $statusLabel . '</strong></span>' .
            '<span style="color: #cbd5e1;">&bull;</span>' .
            $progressBadge .
            ($overdueBadge ? '<span style="color: #cbd5e1;">&bull;</span>' . $overdueBadge : '') .
            '</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('markCompleted')
                ->label('Mark Task Complete')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => ! in_array($this->record->status, [TaskStatus::COMPLETED, TaskStatus::CANCELLED]))
                ->form([
                    Textarea::make('resolution_notes')
                        ->label('Resolution Summary & Field Findings')
                        ->required()
                        ->rows(3)
                        ->placeholder('Describe the outcome of this task, key verifications, or remarks...'),
                ])
                ->action(function (array $data) {
                    try {
                        app(TaskService::class)->completeTask($this->record, $data['resolution_notes']);
                        Notification::make()
                            ->title('Task Completed')
                            ->body("Task #{$this->record->task_number} has been marked complete.")
                            ->success()
                            ->send();

                        $this->refreshFormData(['status', 'resolution_notes', 'completed_at', 'completed_by_id']);
                    } catch (\Illuminate\Validation\ValidationException $e) {
                        Notification::make()
                            ->title('Cannot Complete Task')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('cancelTask')
                ->label('Cancel Task')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn () => ! in_array($this->record->status, [TaskStatus::COMPLETED, TaskStatus::CANCELLED]))
                ->requiresConfirmation()
                ->modalHeading('Cancel Operations Task')
                ->form([
                    Textarea::make('cancellation_reason')
                        ->label('Reason for Cancellation')
                        ->required()
                        ->rows(2)
                        ->placeholder('Explain why this task is being cancelled...'),
                ])
                ->action(function (array $data) {
                    app(TaskService::class)->cancelTask($this->record, $data['cancellation_reason']);
                    Notification::make()
                        ->title('Task Cancelled')
                        ->body("Task #{$this->record->task_number} has been cancelled.")
                        ->warning()
                        ->send();

                    $this->refreshFormData(['status', 'cancellation_reason']);
                }),

            DeleteAction::make(),
        ];
    }
}
