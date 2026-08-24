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
use App\Domain\Maintenance\Services\MaintenanceBillingService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
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

    protected string $view = 'filament.resources.operations.maintenance-requests.verification-audit-relation-manager';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('audit_number')
            ->heading('Quality Verification Audits')
            ->description(function (RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();
                if (! $ticket) {
                    return 'On-site quality verification audits and inspections.';
                }
                if ($ticket->isWorkCompleted()) {
                    return 'Work completed and verified. On-site quality audits are optional.';
                }

                return 'Optional quality audits. Record sign-off to complete work.';
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
                $this->getMarkWorkCompletedAction(),
                $this->getGenerateClientInvoiceAction(),
                $this->getGenerateVendorBillsAction(),
                $this->getManageClientAcceptanceAction(),
                ActionGroup::make([
                    $this->getTriggerOptionalAuditAction(),
                    $this->getViewPdfModalAction(),
                ])
                ->label('More')
                ->icon('heroicon-m-ellipsis-vertical')
                ->color('gray')
                ->button()
                ->size('sm'),
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
            ->label('Maintenance PDF')
            ->icon('heroicon-o-document-text')
            ->color('gray')
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
            ->label(function (RelationManager $livewire): string {
                $ticket = $livewire->getOwnerRecord();
                if ($ticket?->payer_type?->isDwellyAbsorbed()) {
                    return 'Mark Work Completed (Internal Sign-Off)';
                }
                return 'Mark Work Completed (Client Acceptance)';
            })
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->button()
            ->size('sm')
            ->record(fn (RelationManager $livewire) => $livewire->getOwnerRecord())
            ->visible(function (RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();
                if (! $ticket) return false;

                if ($ticket->isWorkCompleted()) {
                    return false;
                }

                $statusVal = $ticket->status instanceof MaintenanceStatus ? $ticket->status->value : (string) $ticket->status;

                return in_array($statusVal, [
                    'in_progress',
                    'submitted',
                    'vendor_assigned',
                    'quoted',
                    'quotation_approved',
                    'audit_pending',
                    'audit_approved',
                ]);
            })
            ->modalHeading(fn (RelationManager $livewire) => $livewire->getOwnerRecord()?->payer_type?->isDwellyAbsorbed()
                ? 'Internal Maintenance Sign-Off & Work Completion'
                : 'Paying Party Acceptance & Work Completion Sign-Off'
            )
            ->modalDescription(fn (RelationManager $livewire) => $livewire->getOwnerRecord()?->payer_type?->isDwellyAbsorbed()
                ? 'Confirm operational completion of repair work. Cost is absorbed 100% by Dwelly.'
                : 'Upload documentary proof (signed handover sheet, client email, or WhatsApp confirmation) showing the paying party has accepted the completed repair.'
            )
            ->modalSubmitActionLabel('Confirm & Mark Completed')
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
                    ->content(function (RelationManager $livewire): HtmlString {
                        $ticket = $livewire->getOwnerRecord();
                        $ticket?->loadMissing(['owner', 'tenant']);

                        if ($ticket?->payer_type?->isDwellyAbsorbed()) {
                            return new HtmlString("
                                <div style='background: linear-gradient(135deg, #f0fdf4 0%, #f8fafc 100%); border: 1.5px solid #bbf7d0; border-left: 5px solid #16a34a; border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #166534; line-height: 1.5;'>
                                    <div style='display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 6px;'>
                                        <strong>🏢 Internal Repair Sign-Off & Verification</strong>
                                        <span style='font-size: 11px; font-weight: 700; background: #dcfce7; color: #15803d; padding: 3px 8px; border-radius: 9999px; text-transform: uppercase;'>
                                            100% Absorbed by Dwelly
                                        </span>
                                    </div>
                                    <div>
                                        This maintenance ticket is absorbed by Dwelly operations. Confirm on-site repair completion. Documentary proof is optional.
                                    </div>
                                </div>
                            ");
                        }

                        $payerVal = $ticket?->payer_type instanceof PayerType
                            ? $ticket->payer_type->value
                            : (string) ($ticket?->payer_type ?? 'owner');

                        $payerRole = match ($payerVal) {
                            'owner' => 'Property Owner',
                            'tenant' => 'Tenant',
                            'dwelly' => 'Dwelly Operations',
                            default => 'Paying Party',
                        };

                        $payerName = match ($payerVal) {
                            'owner' => $ticket?->owner?->display_name ?: ($ticket?->owner?->name ?: 'Property Owner'),
                            'tenant' => $ticket?->tenant?->display_name ?: ($ticket?->tenant?->name ?: 'Tenant'),
                            'dwelly' => 'Dwelly Operations',
                            default => 'Paying Party',
                        };

                        $badgeColor = match ($payerVal) {
                            'owner' => '#1e40af',
                            'tenant' => '#7c3aed',
                            default => '#0f766e',
                        };

                        $badgeBg = match ($payerVal) {
                            'owner' => '#dbeafe',
                            'tenant' => '#f3e8ff',
                            default => '#ccfbf1',
                        };

                        return new HtmlString("
                            <div style='background: linear-gradient(135deg, #eff6ff 0%, #f8fafc 100%); border: 1.5px solid #bfdbfe; border-left: 5px solid #2563eb; border-radius: 8px; padding: 12px 14px; font-size: 13px; color: #1e3a8a; line-height: 1.5;'>
                                <div style='display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 6px;'>
                                    <strong>📋 Client Repair Sign-Off & Verification</strong>
                                    <span style='font-size: 11px; font-weight: 700; background: {$badgeBg}; color: {$badgeColor}; padding: 3px 8px; border-radius: 9999px; text-transform: uppercase;'>
                                        Paying Party: {$payerRole}
                                    </span>
                                </div>
                                <div>
                                    This maintenance ticket is billed to the <strong>{$payerRole}</strong> (<span style='color: #0f172a; font-weight: 700;'>".e($payerName)."</span>). Please confirm client inspection and upload mandatory documentary proof (signed handover sheet, WhatsApp approval screenshot, or email confirmation).
                                </div>
                            </div>
                        ");
                    }),

                TextInput::make('client_accepted_by_name')
                    ->label(function (RelationManager $livewire): string {
                        $ticket = $livewire->getOwnerRecord();
                        $payerVal = $ticket?->payer_type instanceof PayerType
                            ? $ticket->payer_type->value
                            : (string) ($ticket?->payer_type ?? 'owner');

                        return match ($payerVal) {
                            'owner' => 'Paying Party / Client Name (Property Owner)',
                            'tenant' => 'Paying Party / Client Name (Tenant)',
                            'dwelly' => 'Paying Party / Client Name (Dwelly Operations)',
                            default => 'Paying Party / Client Name',
                        };
                    })
                    ->helperText(function (RelationManager $livewire): string {
                        $ticket = $livewire->getOwnerRecord();
                        $ticket?->loadMissing(['owner', 'tenant']);
                        $payerVal = $ticket?->payer_type instanceof PayerType
                            ? $ticket->payer_type->value
                            : (string) ($ticket?->payer_type ?? 'owner');

                        $payerName = match ($payerVal) {
                            'owner' => $ticket?->owner?->display_name ?: ($ticket?->owner?->name ?: 'Property Owner'),
                            'tenant' => $ticket?->tenant?->display_name ?: ($ticket?->tenant?->name ?: 'Tenant'),
                            'dwelly' => 'Dwelly Operations',
                            default => 'Paying Party',
                        };

                        return match ($payerVal) {
                            'owner' => "Billed to Property Owner ({$payerName}). Enter the owner or authorized representative's name who inspected and accepted the repairs.",
                            'tenant' => "Billed to Tenant ({$payerName}). Enter the tenant's name who inspected and accepted the repairs.",
                            default => "Enter the name of the authorized client / representative confirming satisfactory repair.",
                        };
                    })
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
                    ->placeholder('e.g. Rahul Sharma')
                    ->required(),

                DatePicker::make('client_accepted_at')
                    ->label('Acceptance Date')
                    ->default(now()->toDateString())
                    ->required(),

                Textarea::make('client_acceptance_notes')
                    ->label('Acceptance Remarks / Client Feedback')
                    ->placeholder('e.g. Client inspected repairs on-site and confirmed satisfaction via WhatsApp.')
                    ->rows(2),

                SpatieMediaLibraryFileUpload::make('client_acceptance_proofs')
                    ->collection('client_acceptance_proofs')
                    ->label('Documentary Proof of Acceptance (Images / PDFs)')
                    ->helperText(function (RelationManager $livewire): string {
                        $ticket = $livewire->getOwnerRecord();
                        $isOptional = (bool) $ticket?->is_direct_vendor || (bool) $ticket?->payer_type?->isDwellyAbsorbed();
                        return $isOptional
                            ? 'Optional: Upload confirmation photos, signed notes, or internal documentation if available.'
                            : 'Upload clear photos or PDFs of the signed confirmation, WhatsApp screenshot, or email. (Mandatory for client-billed repairs)';
                    })
                    ->required(fn (RelationManager $livewire): bool => ! (bool) ($livewire->getOwnerRecord()?->is_direct_vendor || $livewire->getOwnerRecord()?->payer_type?->isDwellyAbsorbed()))
                    ->multiple()
                    ->panelLayout('grid')
                    ->imagePreviewHeight('140')
                    ->reorderable()
                    ->openable()
                    ->downloadable()
                    ->previewable()
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();
                $ticket = $ticket->fresh(['items.media']);

                if (! $ticket || $ticket->items->isEmpty()) {
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

                // Attach any uploaded media files
                if (!empty($data['client_acceptance_proofs'])) {
                    foreach ((array) $data['client_acceptance_proofs'] as $file) {
                        if (is_string($file)) {
                            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($file)) {
                                $ticket->addMediaFromDisk($file, 'public')->toMediaCollection('client_acceptance_proofs');
                            } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists($file)) {
                                $ticket->addMediaFromDisk($file, 'local')->toMediaCollection('client_acceptance_proofs');
                            } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists('livewire-tmp/' . $file)) {
                                $ticket->addMediaFromDisk('livewire-tmp/' . $file, 'local')->toMediaCollection('client_acceptance_proofs');
                            } elseif (file_exists($file)) {
                                $ticket->addMedia($file)->toMediaCollection('client_acceptance_proofs');
                            }
                        }
                    }
                }

                // Mark all defect items as completed
                foreach ($ticket->items as $item) {
                    $item->update(['status' => 'completed']);
                }

                Notification::make()
                    ->title('Work Marked Completed')
                    ->body("Paying party acceptance confirmed for Ticket #{$ticket->ticket_number}. Work marked as completed. You may now generate the Client Invoice and Vendor Bills.")
                    ->success()
                    ->send();

                $livewire->dispatch('$refresh');
            });
    }

    protected function getGenerateClientInvoiceAction(): Action
    {
        return Action::make('generateClientInvoice')
            ->label('Generate Client Invoice')
            ->icon('heroicon-o-document-currency-rupee')
            ->color('success')
            ->button()
            ->size('sm')
            ->record(fn (RelationManager $livewire) => $livewire->getOwnerRecord())
            ->visible(function (RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();
                if (! $ticket || $ticket->is_direct_vendor || (bool) $ticket->payer_type?->isDwellyAbsorbed()) return false;
                $hasInvoice = filled($ticket->owner_invoice_id) || filled($ticket->tenant_invoice_id);
                return ! $hasInvoice && ($ticket->isWorkCompleted() || $ticket->hasClientAcceptance());
            })
            ->modalHeading(fn (RelationManager $livewire) => 'Generate Client Invoice – Ticket #' . $livewire->getOwnerRecord()?->ticket_number)
            ->modalDescription('Review the receivable invoice summary below and confirm generation for accounting.')
            ->modalSubmitActionLabel('Confirm & Generate Invoice')
            ->modalWidth('3xl')
            ->fillForm(function (RelationManager $livewire): array {
                $ticket = $livewire->getOwnerRecord();
                return [
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->addDays(7)->toDateString(),
                    'notes' => $ticket ? "Maintenance Invoice for Ticket #{$ticket->ticket_number}: {$ticket->title}" : '',
                ];
            })
            ->form([
                Placeholder::make('invoice_summary_preview')
                    ->hiddenLabel()
                    ->content(fn (RelationManager $livewire) => $this->getInvoiceSummaryHtml($livewire->getOwnerRecord())),

                DatePicker::make('issue_date')
                    ->label('Invoice Issue Date')
                    ->default(now()->toDateString())
                    ->required(),

                DatePicker::make('due_date')
                    ->label('Payment Due Date')
                    ->default(now()->addDays(7)->toDateString())
                    ->required(),

                Textarea::make('notes')
                    ->label('Invoice Notes / Remarks')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();
                $billingService = app(MaintenanceBillingService::class);
                $payerVal = $ticket->payer_type instanceof PayerType
                    ? $ticket->payer_type->value
                    : (string) $ticket->payer_type;

                $billType = match ($payerVal) {
                    'tenant' => 'tenant_invoice',
                    default => 'owner_invoice',
                };

                try {
                    $invoice = $billingService->createMaintenanceInvoice($ticket, $billType, [], [
                        'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                        'due_date' => $data['due_date'] ?? now()->addDays(7)->toDateString(),
                        'notes' => $data['notes'] ?? null,
                    ]);
                    $ticket->update(['status' => MaintenanceStatus::INVOICED]);
                    Notification::make()
                        ->title('Client Invoice Created (Draft)')
                        ->body("Draft Invoice #{$invoice->invoice_number} (₹" . number_format((float) $invoice->grand_total, 2) . ") created and sent to Accounting for review & posting.")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Invoice Generation Error')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }

                $livewire->dispatch('$refresh');
            });
    }

    protected function getGenerateVendorBillsAction(): Action
    {
        return Action::make('generateVendorBills')
            ->label('Generate Vendor Bills')
            ->icon('heroicon-o-receipt-percent')
            ->color('warning')
            ->button()
            ->size('sm')
            ->record(fn (RelationManager $livewire) => $livewire->getOwnerRecord())
            ->visible(function (RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();
                if (! $ticket || $ticket->is_direct_vendor) return false;

                $isCompleted = $ticket->isWorkCompleted() || $ticket->hasClientAcceptance();
                if (! $isCompleted) return false;

                $unbilledQuotes = $ticket->vendorQuotes()->whereNull('bill_id')->count();
                return $unbilledQuotes > 0 || (empty($ticket->bill_id) && $ticket->vendor_party_id);
            })
            ->modalHeading(fn (RelationManager $livewire) => 'Generate Vendor Payable Bills – Ticket #' . $livewire->getOwnerRecord()?->ticket_number)
            ->modalDescription('Review the payable trade contractor bills summary below and confirm generation for accounting.')
            ->modalSubmitActionLabel('Confirm & Generate Bills')
            ->modalWidth('3xl')
            ->fillForm(function (RelationManager $livewire): array {
                $ticket = $livewire->getOwnerRecord();
                return [
                    'issue_date' => now()->toDateString(),
                    'due_date' => now()->addDays(14)->toDateString(),
                    'notes' => $ticket ? "Vendor Bill for Maintenance Ticket #{$ticket->ticket_number}" : '',
                ];
            })
            ->form([
                Placeholder::make('vendor_bills_summary_preview')
                    ->hiddenLabel()
                    ->content(fn (RelationManager $livewire) => $this->getVendorBillsSummaryHtml($livewire->getOwnerRecord())),

                DatePicker::make('issue_date')
                    ->label('Bill Issue Date')
                    ->default(now()->toDateString())
                    ->required(),

                DatePicker::make('due_date')
                    ->label('Payment Due Date')
                    ->default(now()->addDays(14)->toDateString())
                    ->required(),

                Textarea::make('notes')
                    ->label('Bill Notes / Description')
                    ->rows(2)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();
                $billingService = app(MaintenanceBillingService::class);

                try {
                    $bills = $billingService->createAllVendorBillsForRequest($ticket, [
                        'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                        'due_date' => $data['due_date'] ?? now()->addDays(14)->toDateString(),
                        'notes' => $data['notes'] ?? null,
                    ]);
                    $count = count($bills);
                    $total = array_sum(array_map(fn ($b) => (float) $b->grand_total, $bills));
                    Notification::make()
                        ->title('Vendor Bills Created (Draft)')
                        ->body("{$count} Draft Vendor Bill(s) totaling ₹" . number_format($total, 2) . " created and queued for Accounting review & posting.")
                        ->success()
                        ->send();
                } catch (\Throwable $e) {
                    Notification::make()
                        ->title('Bill Generation Error')
                        ->body($e->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();
                }

                $livewire->dispatch('$refresh');
            });
    }

    protected function getInvoiceSummaryHtml(?MaintenanceRequest $ticket): HtmlString
    {
        if (! $ticket) {
            return new HtmlString('');
        }

        $ticket->loadMissing(['owner', 'tenant', 'property', 'currentClientQuote.items', 'clientQuotes.items']);
        $payer = $ticket->payer_type?->getLabel() ?? ucfirst((string) ($ticket->payer_type ?? 'N/A'));
        $payerVal = $ticket->payer_type instanceof PayerType ? $ticket->payer_type->value : (string) $ticket->payer_type;

        $partyName = match ($payerVal) {
            'tenant' => $ticket->tenant?->display_name ?: ($ticket->tenant?->name ?: 'Tenant'),
            'dwelly' => 'Dwelly Operations (Absorbed)',
            default => $ticket->owner?->display_name ?: ($ticket->owner?->name ?: 'Property Owner'),
        };

        $quote = $ticket->currentClientQuote ?? $ticket->clientQuotes()->latest()->first();
        $quoteNumber = $quote?->quote_number ?? 'N/A';
        $propertyName = $ticket->property?->building_name ?? $ticket->property?->code ?? 'Property';

        $itemsHtml = '';
        $totalAmount = 0;

        if ($quote && $quote->items->isNotEmpty()) {
            $isFullAmount = ($payerVal === 'owner' && (float) $quote->owner_amount == (float) $quote->total_amount) ||
                            ($payerVal === 'tenant' && (float) $quote->tenant_amount == (float) $quote->total_amount);

            if ($isFullAmount) {
                foreach ($quote->items as $item) {
                    $qty = (float) ($item->quantity ?: 1);
                    $rate = number_format((float) $item->unit_price, 2);
                    $total = number_format((float) $item->total_price, 2);
                    $totalAmount += (float) $item->total_price;
                    $itemsHtml .= "
                        <tr style='border-bottom: 1px solid rgba(0,0,0,0.06);'>
                            <td style='padding: 6px 8px; font-size: 12px; color: #111827;'>".e($item->description)."</td>
                            <td style='padding: 6px 8px; font-size: 12px; text-align: center; color: #4b5563;'>{$qty}</td>
                            <td style='padding: 6px 8px; font-size: 12px; text-align: right; color: #4b5563;'>₹{$rate}</td>
                            <td style='padding: 6px 8px; font-size: 12px; text-align: right; font-weight: 600; color: #111827;'>₹{$total}</td>
                        </tr>";
                }
            } else {
                $cost = match ($payerVal) {
                    'tenant' => (float) $quote->tenant_amount > 0 ? (float) $quote->tenant_amount : (float) $quote->total_amount,
                    'owner' => (float) $quote->owner_amount > 0 ? (float) $quote->owner_amount : (float) $quote->total_amount,
                    default => (float) $quote->total_amount,
                };
                $totalAmount = $cost;
                $costFmt = number_format($cost, 2);
                $itemsHtml .= "
                    <tr style='border-bottom: 1px solid rgba(0,0,0,0.06);'>
                        <td style='padding: 6px 8px; font-size: 12px; color: #111827;'>Maintenance Service Share: ".e($ticket->title)."</td>
                        <td style='padding: 6px 8px; font-size: 12px; text-align: center; color: #4b5563;'>1</td>
                        <td style='padding: 6px 8px; font-size: 12px; text-align: right; color: #4b5563;'>₹{$costFmt}</td>
                        <td style='padding: 6px 8px; font-size: 12px; text-align: right; font-weight: 600; color: #111827;'>₹{$costFmt}</td>
                    </tr>";
            }
        } else {
            $cost = match ($payerVal) {
                'tenant' => $ticket->tenant_amount > 0 ? (float) $ticket->tenant_amount : (float) $ticket->total_cost,
                'owner' => $ticket->owner_amount > 0 ? (float) $ticket->owner_amount : (float) $ticket->total_cost,
                default => (float) $ticket->total_cost,
            };
            $totalAmount = $cost;
            $costFmt = number_format($cost, 2);
            $itemsHtml .= "
                <tr style='border-bottom: 1px solid rgba(0,0,0,0.06);'>
                    <td style='padding: 6px 8px; font-size: 12px; color: #111827;'>Maintenance Service: ".e($ticket->title)."</td>
                    <td style='padding: 6px 8px; font-size: 12px; text-align: center; color: #4b5563;'>1</td>
                    <td style='padding: 6px 8px; font-size: 12px; text-align: right; color: #4b5563;'>₹{$costFmt}</td>
                    <td style='padding: 6px 8px; font-size: 12px; text-align: right; font-weight: 600; color: #111827;'>₹{$costFmt}</td>
                </tr>";
        }

        $totalFmt = number_format($totalAmount, 2);

        return new HtmlString("
            <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px; margin-bottom: 12px;'>
                <div style='display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;'>
                    <div>
                        <span style='font-size: 11px; text-transform: uppercase; color: #2563eb; font-weight: 700; letter-spacing: 0.05em;'>Client Invoice Summary</span>
                        <h4 style='font-size: 14px; font-weight: 700; color: #0f172a; margin: 2px 0 0 0;'>Ticket #{$ticket->ticket_number} - ".e($ticket->title)."</h4>
                    </div>
                    <div style='text-align: right;'>
                        <span style='font-size: 11px; color: #64748b;'>Receivable Amount</span>
                        <div style='font-size: 16px; font-weight: 800; color: #1e40af;'>₹{$totalFmt}</div>
                    </div>
                </div>

                <div style='display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 10px; font-size: 12px; margin-bottom: 12px; background: #ffffff; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0;'>
                    <div>
                        <span style='color: #64748b; font-size: 11px;'>Billed Party:</span><br>
                        <strong style='color: #0f172a;'>".e($partyName)."</strong>
                    </div>
                    <div>
                        <span style='color: #64748b; font-size: 11px;'>Payer Type:</span><br>
                        <strong style='color: #0f172a;'>".e($payer)."</strong>
                    </div>
                    <div>
                        <span style='color: #64748b; font-size: 11px;'>Quotation Ref:</span><br>
                        <strong style='color: #0f172a;'>".e($quoteNumber)."</strong>
                    </div>
                </div>

                <table style='width: 100%; border-collapse: collapse; background: #ffffff; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0;'>
                    <thead>
                        <tr style='background: #f1f5f9; border-bottom: 1px solid #e2e8f0;'>
                            <th style='padding: 6px 8px; font-size: 11px; font-weight: 700; color: #475569; text-align: left;'>Line Item Description</th>
                            <th style='padding: 6px 8px; font-size: 11px; font-weight: 700; color: #475569; text-align: center; width: 50px;'>Qty</th>
                            <th style='padding: 6px 8px; font-size: 11px; font-weight: 700; color: #475569; text-align: right; width: 90px;'>Rate</th>
                            <th style='padding: 6px 8px; font-size: 11px; font-weight: 700; color: #475569; text-align: right; width: 100px;'>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$itemsHtml}
                    </tbody>
                    <tfoot>
                        <tr style='background: #f8fafc; border-top: 2px solid #e2e8f0;'>
                            <td colspan='3' style='padding: 8px; font-size: 12px; font-weight: 700; text-align: right; color: #0f172a;'>Grand Total (INR):</td>
                            <td style='padding: 8px; font-size: 13px; font-weight: 800; text-align: right; color: #1e40af;'>₹{$totalFmt}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        ");
    }

    protected function getVendorBillsSummaryHtml(?MaintenanceRequest $ticket): HtmlString
    {
        if (! $ticket) {
            return new HtmlString('');
        }

        $ticket->loadMissing(['vendorQuotes.vendor', 'vendor']);
        $quote = $ticket->currentClientQuote ?? $ticket->clientQuotes()->latest()->first();
        $awardedIds = (array) ($quote?->awarded_vendor_quote_ids ?? []);

        $quotesQuery = $ticket->vendorQuotes()->whereNull('bill_id');
        if (! empty($awardedIds)) {
            $quotes = $quotesQuery->whereIn('id', $awardedIds)->get();
        } elseif ($ticket->vendorQuotes()->where('is_awarded', true)->exists()) {
            $quotes = $quotesQuery->where('is_awarded', true)->get();
        } else {
            $quotes = $quotesQuery->get();
        }

        $rowsHtml = '';
        $totalPayable = 0;

        foreach ($quotes as $vq) {
            $vendorName = $vq->vendor?->display_name ?: ($vq->vendor?->name ?: 'Trade Contractor');
            $tradeTitle = $vq->trade_title ?: 'Repair Trade Service';
            $woNumber = $vq->work_order_number ?: 'Pending WO';
            $cost = (float) ($vq->final_cost ?? $vq->quoted_cost);
            $totalPayable += $cost;
            $costFmt = number_format($cost, 2);

            $rowsHtml .= "
                <tr style='border-bottom: 1px solid rgba(0,0,0,0.06);'>
                    <td style='padding: 6px 8px; font-size: 12px; font-weight: 600; color: #111827;'>".e($vendorName)."</td>
                    <td style='padding: 6px 8px; font-size: 12px; color: #4b5563;'>".e($tradeTitle)."</td>
                    <td style='padding: 6px 8px; font-size: 12px; text-align: center; color: #2563eb; font-weight: 600;'>".e($woNumber)."</td>
                    <td style='padding: 6px 8px; font-size: 12px; text-align: right; font-weight: 600; color: #b91c1c;'>₹{$costFmt}</td>
                </tr>";
        }

        // Fallback if legacy single vendor ticket
        if ($quotes->isEmpty() && $ticket->vendor_party_id && empty($ticket->bill_id)) {
            $vendorName = $ticket->vendor?->display_name ?: ($ticket->vendor?->name ?: 'Vendor Contractor');
            $cost = (float) ($ticket->vendor_cost > 0 ? $ticket->vendor_cost : $ticket->total_cost);
            $totalPayable += $cost;
            $costFmt = number_format($cost, 2);

            $rowsHtml .= "
                <tr style='border-bottom: 1px solid rgba(0,0,0,0.06);'>
                    <td style='padding: 6px 8px; font-size: 12px; font-weight: 600; color: #111827;'>".e($vendorName)."</td>
                    <td style='padding: 6px 8px; font-size: 12px; color: #4b5563;'>".e($ticket->title)."</td>
                    <td style='padding: 6px 8px; font-size: 12px; text-align: center; color: #6b7280;'>N/A</td>
                    <td style='padding: 6px 8px; font-size: 12px; text-align: right; font-weight: 600; color: #b91c1c;'>₹{$costFmt}</td>
                </tr>";
        }

        $totalFmt = number_format($totalPayable, 2);
        $billCount = $quotes->isNotEmpty() ? $quotes->count() : ($ticket->vendor_party_id ? 1 : 0);

        return new HtmlString("
            <div style='background: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 14px; margin-bottom: 12px;'>
                <div style='display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; border-bottom: 1px solid #fecdd3; padding-bottom: 8px;'>
                    <div>
                        <span style='font-size: 11px; text-transform: uppercase; color: #e11d48; font-weight: 700; letter-spacing: 0.05em;'>Vendor Bills Summary ({$billCount} Bill(s))</span>
                        <h4 style='font-size: 14px; font-weight: 700; color: #0f172a; margin: 2px 0 0 0;'>Ticket #{$ticket->ticket_number} - ".e($ticket->title)."</h4>
                    </div>
                    <div style='text-align: right;'>
                        <span style='font-size: 11px; color: #64748b;'>Total Payable</span>
                        <div style='font-size: 16px; font-weight: 800; color: #b91c1c;'>₹{$totalFmt}</div>
                    </div>
                </div>

                <table style='width: 100%; border-collapse: collapse; background: #ffffff; border-radius: 6px; overflow: hidden; border: 1px solid #e2e8f0;'>
                    <thead>
                        <tr style='background: #f1f5f9; border-bottom: 1px solid #e2e8f0;'>
                            <th style='padding: 6px 8px; font-size: 11px; font-weight: 700; color: #475569; text-align: left;'>Contractor / Vendor</th>
                            <th style='padding: 6px 8px; font-size: 11px; font-weight: 700; color: #475569; text-align: left;'>Trade / Scope</th>
                            <th style='padding: 6px 8px; font-size: 11px; font-weight: 700; color: #475569; text-align: center; width: 120px;'>Work Order</th>
                            <th style='padding: 6px 8px; font-size: 11px; font-weight: 700; color: #475569; text-align: right; width: 100px;'>Payable</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$rowsHtml}
                    </tbody>
                    <tfoot>
                        <tr style='background: #f8fafc; border-top: 2px solid #e2e8f0;'>
                            <td colspan='3' style='padding: 8px; font-size: 12px; font-weight: 700; text-align: right; color: #0f172a;'>Total Payable (INR):</td>
                            <td style='padding: 8px; font-size: 13px; font-weight: 800; text-align: right; color: #b91c1c;'>₹{$totalFmt}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        ");
    }

    protected function getManageClientAcceptanceAction(): Action
    {
        return Action::make('manageClientAcceptance')
            ->label('Manage Acceptance Proof')
            ->icon('heroicon-o-paper-clip')
            ->color('primary')
            ->button()
            ->size('sm')
            ->record(fn (RelationManager $livewire) => $livewire->getOwnerRecord())
            ->visible(fn (RelationManager $livewire) => (bool) ($livewire->getOwnerRecord()?->isWorkCompleted() || $livewire->getOwnerRecord()?->hasClientAcceptance()))
            ->modalHeading('Paying Party Acceptance Proof & Remarks')
            ->modalDescription('View, upload, or update client acceptance proof documents (signed sheets, WhatsApp confirmations, emails) and sign-off notes.')
            ->modalSubmitActionLabel('Save Acceptance Proof')
            ->modalWidth('2xl')
            ->fillForm(function (RelationManager $livewire): array {
                $ticket = $livewire->getOwnerRecord();
                return [
                    'client_accepted_by_name' => $ticket->client_accepted_by_name,
                    'client_accepted_at' => $ticket->client_accepted_at ?: now(),
                    'client_acceptance_notes' => $ticket->client_acceptance_notes,
                ];
            })
            ->form([
                TextInput::make('client_accepted_by_name')
                    ->label(function (RelationManager $livewire): string {
                        $ticket = $livewire->getOwnerRecord();
                        $payerVal = $ticket?->payer_type instanceof PayerType
                            ? $ticket->payer_type->value
                            : (string) ($ticket?->payer_type ?? 'owner');

                        return match ($payerVal) {
                            'owner' => 'Paying Party / Client Name (Property Owner)',
                            'tenant' => 'Paying Party / Client Name (Tenant)',
                            'dwelly' => 'Paying Party / Client Name (Dwelly Operations)',
                            default => 'Paying Party / Client Name',
                        };
                    })
                    ->helperText(function (RelationManager $livewire): string {
                        $ticket = $livewire->getOwnerRecord();
                        $ticket?->loadMissing(['owner', 'tenant']);
                        $payerVal = $ticket?->payer_type instanceof PayerType
                            ? $ticket->payer_type->value
                            : (string) ($ticket?->payer_type ?? 'owner');

                        $payerName = match ($payerVal) {
                            'owner' => $ticket?->owner?->display_name ?: ($ticket?->owner?->name ?: 'Property Owner'),
                            'tenant' => $ticket?->tenant?->display_name ?: ($ticket?->tenant?->name ?: 'Tenant'),
                            'dwelly' => 'Dwelly Operations',
                            default => 'Paying Party',
                        };

                        return match ($payerVal) {
                            'owner' => "Billed to Property Owner ({$payerName}).",
                            'tenant' => "Billed to Tenant ({$payerName}).",
                            default => "Authorized client or representative name.",
                        };
                    })
                    ->placeholder('e.g. Rahul Sharma')
                    ->required(),

                DatePicker::make('client_accepted_at')
                    ->label('Acceptance Date')
                    ->required(),

                Textarea::make('client_acceptance_notes')
                    ->label('Acceptance Remarks / Client Feedback')
                    ->placeholder('e.g. Client inspected repairs on-site and confirmed satisfaction via WhatsApp.')
                    ->rows(2),

                SpatieMediaLibraryFileUpload::make('client_acceptance_proofs')
                    ->collection('client_acceptance_proofs')
                    ->label('Documentary Proof of Acceptance (Images / PDFs)')
                    ->helperText(function (RelationManager $livewire): string {
                        $ticket = $livewire->getOwnerRecord();
                        return (bool) $ticket?->is_direct_vendor
                            ? 'Optional for direct repairs: Upload confirmation photos, signed notes, or chat approval if available.'
                            : 'Upload clear photos or PDFs of the signed confirmation, WhatsApp screenshot, or email. (Mandatory for Dwelly-coordinated)';
                    })
                    ->required(fn (RelationManager $livewire): bool => ! (bool) $livewire->getOwnerRecord()?->is_direct_vendor)
                    ->multiple()
                    ->panelLayout('grid')
                    ->imagePreviewHeight('140')
                    ->reorderable()
                    ->openable()
                    ->downloadable()
                    ->previewable()
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, RelationManager $livewire) {
                $ticket = $livewire->getOwnerRecord();

                $ticket->update([
                    'client_accepted_by_name' => $data['client_accepted_by_name'] ?? $ticket->client_accepted_by_name,
                    'client_accepted_at' => $data['client_accepted_at'] ?? ($ticket->client_accepted_at ?? now()),
                    'client_acceptance_notes' => $data['client_acceptance_notes'] ?? $ticket->client_acceptance_notes,
                ]);

                if (!empty($data['client_acceptance_proofs'])) {
                    foreach ((array) $data['client_acceptance_proofs'] as $file) {
                        if (is_string($file)) {
                            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($file)) {
                                $ticket->addMediaFromDisk($file, 'public')->toMediaCollection('client_acceptance_proofs');
                            } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists($file)) {
                                $ticket->addMediaFromDisk($file, 'local')->toMediaCollection('client_acceptance_proofs');
                            } elseif (\Illuminate\Support\Facades\Storage::disk('local')->exists('livewire-tmp/' . $file)) {
                                $ticket->addMediaFromDisk('livewire-tmp/' . $file, 'local')->toMediaCollection('client_acceptance_proofs');
                            } elseif (file_exists($file)) {
                                $ticket->addMedia($file)->toMediaCollection('client_acceptance_proofs');
                            }
                        }
                    }
                }

                Notification::make()
                    ->title('Acceptance Proof Updated')
                    ->body("Client acceptance proof documents and notes for Ticket #{$ticket->ticket_number} have been saved successfully.")
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
