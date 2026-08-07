<?php

namespace App\Filament\Resources\Operations\MaintenanceRequestResource\Pages;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Services\MaintenanceAuditTriggerService;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\HtmlString;

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
            Action::make('approveQuotation')
                ->label('Approve Quotation')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn () => !$this->record->is_direct_vendor && in_array($this->record->status, [
                    MaintenanceStatus::SUBMITTED,
                    MaintenanceStatus::VENDOR_ASSIGNED,
                    MaintenanceStatus::QUOTED,
                    MaintenanceStatus::QUOTATION_PENDING,
                ]))
                ->form([
                    Textarea::make('quotation_approval_notes')
                        ->label('Approval Confirmation Notes')
                        ->placeholder('e.g. Approved via WhatsApp / Email confirmation by Owner')
                        ->required(),

                    SpatieMediaLibraryFileUpload::make('quotation_approval_proofs')
                        ->collection('quotation_approval_proofs')
                        ->multiple()
                        ->required()
                        ->label('Quotation Approval Proof (Mandatory: Upload Email / WhatsApp screenshot or signed quotation)'),
                ])
                ->action(function (array $data) {
                    $record = $this->getRecord();
                    $record->update([
                        'quotation_status' => 'approved',
                        'quotation_approved_at' => now(),
                        'quotation_approval_notes' => $data['quotation_approval_notes'] ?? null,
                        'status' => MaintenanceStatus::QUOTATION_APPROVED,
                    ]);

                    Notification::make()
                        ->title('Quotation Approved')
                        ->body("Quotation for ticket #{$record->ticket_number} has been approved with proof attached.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status', 'quotation_status', 'quotation_approved_at', 'quotation_approval_notes']);
                }),

            Action::make('startRepair')
                ->label('Proceed with Repair')
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('primary')
                ->visible(fn () => in_array($this->record->status, [
                    MaintenanceStatus::SUBMITTED,
                    MaintenanceStatus::QUOTATION_APPROVED,
                ]) || ($this->record->is_direct_vendor && $this->record->status === MaintenanceStatus::SUBMITTED))
                ->action(function () {
                    $record = $this->getRecord();
                    $record->update([
                        'status' => MaintenanceStatus::IN_PROGRESS,
                    ]);

                    Notification::make()
                        ->title('Repair In Progress')
                        ->body("Status updated to Repair In Progress.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('markWorkCompleted')
                ->label('Mark Repair Completed')
                ->icon('heroicon-o-check-badge')
                ->color('teal')
                ->visible(fn () => in_array($this->record->status, [
                    MaintenanceStatus::IN_PROGRESS,
                    MaintenanceStatus::QUOTATION_APPROVED,
                ]))
                ->action(function () {
                    $record = $this->getRecord();
                    $record->update([
                        'status' => MaintenanceStatus::WORK_COMPLETED,
                        'completed_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Work Completed')
                        ->body("Repair marked as completed. You can now trigger the post-repair verification audit.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('submitInvoice')
                ->label('Submit Invoice to Payer')
                ->icon('heroicon-o-document-text')
                ->color('indigo')
                ->visible(fn () => !$this->record->is_direct_vendor && in_array($this->record->status, [
                    MaintenanceStatus::WORK_COMPLETED,
                    MaintenanceStatus::AUDIT_PENDING,
                    MaintenanceStatus::AUDIT_APPROVED,
                ]))
                ->action(function () {
                    $record = $this->getRecord();

                    // Check audit approval
                    if (!$record->triggered_audit_id || !$record->triggeredAudit || !in_array($record->triggeredAudit->status?->value ?? (string)$record->triggeredAudit->status, ['approved', 'completed'])) {
                        Notification::make()
                            ->title('Audit Approval Required')
                            ->body('Post-repair verification audit must be completed and approved before submitting the invoice to the tenant/owner.')
                            ->warning()
                            ->persistent()
                            ->send();
                        return;
                    }

                    $record->update([
                        'status' => MaintenanceStatus::INVOICED,
                    ]);

                    Notification::make()
                        ->title('Invoice Submitted')
                        ->body("Invoice submitted to payer for ticket #{$record->ticket_number}.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('payVendor')
                ->label('Record Vendor Payment')
                ->icon('heroicon-o-currency-rupee')
                ->color('success')
                ->visible(fn () => !$this->record->is_direct_vendor && in_array($this->record->status, [
                    MaintenanceStatus::INVOICED,
                    MaintenanceStatus::AUDIT_APPROVED,
                ]))
                ->action(function () {
                    $record = $this->getRecord();

                    $record->update([
                        'status' => MaintenanceStatus::RESOLVED,
                        'resolved_at' => now(),
                    ]);

                    Notification::make()
                        ->title('Vendor Paid & Resolved')
                        ->body("Vendor payment recorded for ticket #{$record->ticket_number}.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('closeTicket')
                ->label('Close Maintenance Request')
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Close Maintenance Ticket')
                ->modalDescription('Are you sure you want to close this maintenance ticket?')
                ->visible(fn () => !in_array($this->record->status, [MaintenanceStatus::CLOSED, MaintenanceStatus::CANCELLED]))
                ->action(function () {
                    $record = $this->getRecord();
                    $service = app(MaintenanceAuditTriggerService::class);
                    $errors = $service->validateForAuditTrigger($record);

                    if (!empty($errors)) {
                        $bulletList = implode("<br>&bull; ", $errors);
                        Notification::make()
                            ->title('Cannot Close Maintenance Request')
                            ->body(new HtmlString("Please complete mandatory ticket details:<br>&bull; {$bulletList}"))
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
                        'status' => MaintenanceStatus::CLOSED,
                        'resolved_at' => $record->resolved_at ?? now(),
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
        if (!empty($data['vendor_party_id']) && in_array($this->record->status, [MaintenanceStatus::DRAFT, MaintenanceStatus::SUBMITTED])) {
            $data['status'] = MaintenanceStatus::VENDOR_ASSIGNED;
            $data['assigned_at'] = now();
        }

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
