<?php

namespace App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Services\MaintenanceAuditTriggerService;
use App\Domain\Property\Models\PropertyInventory;
use App\Domain\Property\Models\PropertyRoom;
use App\Domain\Property\Models\PropertyUtility;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Forms\Components\Placeholder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class VerificationAuditRelationManager extends RelationManager
{
    protected static string $relationship = 'verificationAudits';

    protected static ?string $title = 'Completion, Report & Verification';

    protected static \BackedEnum|string|null $icon = 'heroicon-o-check-badge';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('audit_number')
            ->heading('Quality Verification Audits (Optional)')
            ->description(function (RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();
                if (!$ticket) return '';

                $completionText = $ticket->isWorkCompleted()
                    ? '✅ Work marked completed with client acceptance.'
                    : '⏳ Awaiting paying party documentary acceptance to finalize ticket completion.';

                $auditText = filled($ticket->triggered_audit_id)
                    ? 'Quality verification audit is active.'
                    : 'Quality audits are optional.';

                return "{$completionText} {$auditText}";
            })
            ->columns([
                TextColumn::make('audit_number')
                    ->label('Audit Number')
                    ->weight('bold')
                    ->copyable()
                    ->searchable(),

                TextColumn::make('audit_type')
                    ->label('Audit Type')
                    ->badge(),

                TextColumn::make('status')
                    ->label('Audit Status')
                    ->badge(),

                TextColumn::make('inspector.name')
                    ->label('Assigned Inspector')
                    ->placeholder('Unassigned'),

                TextColumn::make('reviewer.name')
                    ->label('Reviewer')
                    ->placeholder('Unassigned'),

                TextColumn::make('created_at')
                    ->label('Triggered Date')
                    ->dateTime('M d, Y h:i A')
                    ->sortable(),
            ])
            ->headerActions([
                $this->getViewPdfModalAction(),
                $this->getMarkWorkCompletedAction(),
                $this->getTriggerOptionalAuditAction(),
            ])
            ->recordActions([
                Action::make('openAudit')
                    ->label('Open Audit')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('primary')
                    ->url(fn (Audit $record): string => \App\Filament\Resources\Operations\AuditResource::getUrl('edit', ['record' => $record])),
            ])
            ->emptyStateHeading('No Quality Verification Audit Initiated')
            ->emptyStateDescription('On-site quality audits are optional. Upload paying party acceptance proof to mark work completed directly, or trigger a quality audit if inspection is required.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }

    protected function getViewPdfModalAction(): Action
    {
        return Action::make('viewPdfDossier')
            ->label('View Maintenance PDF')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->button()
            ->size('sm')
            ->modalHeading(fn (RelationManager $livewire) => 'Maintenance Dossier: Ticket #' . $livewire->getOwnerRecord()?->ticket_number)
            ->modalWidth('7xl')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalContent(fn (RelationManager $livewire) => view('filament.forms.components.maintenance-report-modal', [
                'ticket' => $livewire->getOwnerRecord(),
            ]));
    }

    protected function getMarkWorkCompletedAction(): Action
    {
        return Action::make('recordClientAcceptanceAndComplete')
            ->label('Mark Work Completed (Client Acceptance)')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->button()
            ->size('sm')
            ->visible(function (RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();
                if (!$ticket) return false;

                $statusVal = $ticket->status instanceof MaintenanceStatus ? $ticket->status->value : (string) $ticket->status;

                return !$ticket->isWorkCompleted() && in_array($statusVal, [
                    'in_progress',
                    'submitted',
                    'vendor_assigned',
                    'quoted',
                    'quotation_approved',
                    'audit_pending',
                    'audit_approved',
                ]);
            })
            ->modalHeading('Paying Party Acceptance & Work Completion Sign-Off')
            ->modalDescription('Upload documentary proof (signed handover sheet, client email, or WhatsApp confirmation) showing the paying party has accepted the completed repair.')
            ->modalSubmitActionLabel('Confirm Acceptance & Mark Completed')
            ->modalWidth('2xl')
            ->fillForm(function (RelationManager $livewire): array {
                $ticket = $livewire->getOwnerRecord();
                $ticket->loadMissing(['owner', 'tenant']);

                $defaultName = '';
                $payerType = $ticket->payer_type instanceof PayerType ? $ticket->payer_type->value : (string) $ticket->payer_type;

                if ($payerType === 'owner') {
                    $defaultName = $ticket->owner?->display_name ?: ($ticket->owner?->name ?: 'Property Owner');
                } elseif ($payerType === 'tenant') {
                    $defaultName = $ticket->tenant?->display_name ?: ($ticket->tenant?->name ?: 'Tenant');
                } elseif ($payerType === 'dwelly') {
                    $defaultName = 'Dwelly Operations';
                }

                return [
                    'client_accepted_by_name' => $ticket->client_accepted_by_name ?: $defaultName,
                    'client_accepted_at' => $ticket->client_accepted_at ?: now(),
                    'client_acceptance_notes' => $ticket->client_acceptance_notes,
                ];
            })
            ->form([
                Placeholder::make('acceptance_instructions')
                    ->hiddenLabel()
                    ->content(new HtmlString('
                        <div style="background: rgba(37, 99, 235, 0.05); border: 1px solid rgba(37, 99, 235, 0.2); border-radius: 6px; padding: 10px 12px; font-size: 12px; color: #1e3a8a;">
                            📌 <strong>Client Sign-Off Requirement:</strong> Upload signed delivery notes, WhatsApp chat approvals, or email confirmation from the paying party to formally close this maintenance ticket.
                        </div>
                    ')),

                TextInput::make('client_accepted_by_name')
                    ->label('Paying Party / Client Name')
                    ->default(function (RelationManager $livewire): string {
                        $ticket = $livewire->getOwnerRecord();
                        if (!$ticket) return '';
                        $ticket->loadMissing(['owner', 'tenant']);
                        if (filled($ticket->client_accepted_by_name)) {
                            return $ticket->client_accepted_by_name;
                        }
                        $payerType = $ticket->payer_type instanceof PayerType ? $ticket->payer_type->value : (string) $ticket->payer_type;
                        if ($payerType === 'owner') {
                            return $ticket->owner?->display_name ?: ($ticket->owner?->name ?: 'Property Owner');
                        } elseif ($payerType === 'tenant') {
                            return $ticket->tenant?->display_name ?: ($ticket->tenant?->name ?: 'Tenant');
                        } elseif ($payerType === 'dwelly') {
                            return 'Dwelly Operations';
                        }
                        return '';
                    })
                    ->placeholder('e.g. Rahul Sharma (Owner / Tenant)')
                    ->required(),

                DateTimePicker::make('client_accepted_at')
                    ->label('Acceptance Date & Time')
                    ->default(now())
                    ->required(),

                Textarea::make('client_acceptance_notes')
                    ->label('Acceptance Remarks / Client Feedback')
                    ->placeholder('e.g. Client inspected master bathroom seepage fix and confirmed satisfaction via WhatsApp.')
                    ->rows(2),

                SpatieMediaLibraryFileUpload::make('client_acceptance_proofs')
                    ->collection('client_acceptance_proofs')
                    ->label('Documentary Proof of Acceptance (Images / PDFs)')
                    ->helperText('Upload clear photos or PDFs of the signed confirmation, WhatsApp screenshot, or email.')
                    ->multiple()
                    ->panelLayout('grid')
                    ->imagePreviewHeight('140')
                    ->reorderable()
                    ->openable()
                    ->downloadable()
                    ->previewable()
                    ->required(fn (RelationManager $livewire) => !$livewire->getOwnerRecord()?->hasMedia('client_acceptance_proofs'))
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();
                $ticket->loadMissing('items');

                if ($ticket->items->isEmpty()) {
                    Notification::make()
                        ->title('No Defect Items')
                        ->body('There are no defect items recorded on this maintenance ticket.')
                        ->warning()
                        ->send();
                    return;
                }

                // Verify that items have resolution notes and after photos
                $incompleteItems = [];
                foreach ($ticket->items as $index => $item) {
                    $itemNum = $index + 1;
                    $hasPhotos = $item->hasMedia('repaired_photos');
                    $hasAction = filled($item->repair_action);

                    if (!$hasPhotos || !$hasAction) {
                        $targetName = 'Item #' . $itemNum;
                        if ($item->itemable instanceof PropertyRoom) {
                            $targetName = $item->itemable->custom_name ?: ($item->itemable->roomDefinition?->name ?? "Room #{$itemNum}");
                        } elseif ($item->itemable instanceof PropertyInventory) {
                            $targetName = $item->itemable->inventoryType?->name ?? "Inventory #{$itemNum}";
                        }
                        $missing = [];
                        if (!$hasAction) $missing[] = 'resolution notes';
                        if (!$hasPhotos) $missing[] = 'after-repair photos';
                        $incompleteItems[] = "<strong>{$targetName}</strong> (missing " . implode(' & ', $missing) . ")";
                    }
                }

                if (!empty($incompleteItems)) {
                    $listHtml = implode('<br>&bull; ', $incompleteItems);
                    Notification::make()
                        ->title('Incomplete Repair Items')
                        ->body(new HtmlString("Please ensure all items have repair details and after-repair proof:<br>&bull; {$listHtml}"))
                        ->danger()
                        ->persistent()
                        ->send();
                    return;
                }

                // Update ticket with client acceptance metadata
                $ticket->update([
                    'client_accepted_by_name' => $data['client_accepted_by_name'] ?? null,
                    'client_accepted_at' => $data['client_accepted_at'] ?? now(),
                    'client_acceptance_notes' => $data['client_acceptance_notes'] ?? null,
                    'completed_at' => now(),
                    'status' => MaintenanceStatus::WORK_COMPLETED,
                ]);

                // Mark all defect items as completed
                foreach ($ticket->items as $item) {
                    $item->update(['status' => 'completed']);
                }

                Notification::make()
                    ->title('Work Marked Completed')
                    ->body("Paying party acceptance confirmed for Ticket #{$ticket->ticket_number}. Work marked as completed.")
                    ->success()
                    ->send();

                $livewire->dispatch('$refresh');
            });
    }

    protected function getTriggerOptionalAuditAction(): Action
    {
        return Action::make('triggerOptionalAudit')
            ->label('Trigger Quality Audit (Optional)')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('gray')
            ->button()
            ->size('sm')
            ->visible(function (RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();
                if (!$ticket) return false;

                $statusVal = $ticket->status instanceof MaintenanceStatus ? $ticket->status->value : (string) $ticket->status;

                return empty($ticket->triggered_audit_id) && !in_array($statusVal, [
                    'closed',
                    'cancelled',
                ]);
            })
            ->requiresConfirmation()
            ->modalHeading('Initiate Quality Verification Audit (Optional)')
            ->modalDescription('Trigger an optional on-site verification audit for quality inspectors to inspect the completed physical repairs.')
            ->modalSubmitActionLabel('Trigger Audit')
            ->action(function (RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();
                $ticket->loadMissing('items');

                if ($ticket->items->isEmpty()) {
                    Notification::make()
                        ->title('No Items Found')
                        ->body('There are no defect items recorded on this maintenance ticket.')
                        ->warning()
                        ->send();
                    return;
                }

                $service = app(MaintenanceAuditTriggerService::class);
                $audit = $service->triggerAudit($ticket);

                Notification::make()
                    ->title('Verification Audit Initiated')
                    ->body("Quality Verification Audit #{$audit->audit_number} created successfully.")
                    ->success()
                    ->send();

                $livewire->dispatch('$refresh');
            });
    }
}
