<?php

namespace App\Filament\Resources\Operations\MaintenanceRequestResource\Pages;

use App\Domain\Maintenance\Services\MaintenanceAuditTriggerService;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceRequest extends EditRecord
{
    protected static string $resource = MaintenanceRequestResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->record->items()->count() === 0) {
            $this->record->items()->create([
                'status' => 'pending',
            ]);
            $this->fillForm();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('closeTicket')
                ->label('Close Maintenance Request')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Close Maintenance Request')
                ->modalDescription('Are you sure you want to close this maintenance ticket? Ensure all repair work, financial settlements, and audit checks are completed.')
                ->visible(fn () => !in_array($this->record->status, [\App\Domain\Maintenance\Enums\MaintenanceStatus::CLOSED, \App\Domain\Maintenance\Enums\MaintenanceStatus::CANCELLED]))
                ->action(function () {
                    $record = $this->getRecord();
                    $service = app(MaintenanceAuditTriggerService::class);
                    $errors = $service->validateForAuditTrigger($record);

                    if (!empty($errors)) {
                        $bulletList = implode("<br>&bull; ", $errors);
                        Notification::make()
                            ->title('Cannot Close Maintenance Request')
                            ->body(new \Illuminate\Support\HtmlString("Please complete all mandatory information before closing the ticket:<br>&bull; {$bulletList}"))
                            ->danger()
                            ->persistent()
                            ->send();
                        return;
                    }

                    if ($record->triggered_audit_id && $record->triggeredAudit) {
                        $auditStatus = $record->triggeredAudit->status;
                        $statusVal = $auditStatus instanceof \App\Domain\Audit\Enums\AuditStatus ? $auditStatus->value : (string) $auditStatus;
                        if (!in_array($statusVal, ['approved', 'completed'])) {
                            Notification::make()
                                ->title('Cannot Close Maintenance Request')
                                ->body('The linked post-repair verification audit is still pending approval. Please approve the audit first.')
                                ->warning()
                                ->persistent()
                                ->send();
                            return;
                        }
                    }

                    $record->update([
                        'status' => \App\Domain\Maintenance\Enums\MaintenanceStatus::CLOSED,
                        'resolved_at' => now(),
                        'completed_at' => $record->completed_at ?? now(),
                    ]);

                    if ($record->triggered_audit_id && $record->triggeredAudit) {
                        app(\App\Domain\Audit\Services\AuditReviewService::class)->lockAudit($record->triggeredAudit, auth()->user());
                    }

                    Notification::make()
                        ->title('Maintenance Request Closed')
                        ->body("Ticket #{$record->ticket_number} has been closed successfully.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (!empty($data['vendor_party_id']) && in_array($this->record->status, [\App\Domain\Maintenance\Enums\MaintenanceStatus::DRAFT, \App\Domain\Maintenance\Enums\MaintenanceStatus::SUBMITTED])) {
            $data['status'] = \App\Domain\Maintenance\Enums\MaintenanceStatus::VENDOR_ASSIGNED;
            $data['assigned_at'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
