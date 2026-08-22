<?php

namespace App\Filament\Resources\Operations\MaintenanceRequestResource\Pages;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Services\MaintenanceAuditTriggerService;
use App\Domain\Maintenance\Services\MaintenanceBillingService;
use App\Filament\Resources\Operations\MaintenanceRequestResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class EditMaintenanceRequest extends EditRecord
{
    protected static string $resource = MaintenanceRequestResource::class;

    public function getSubheading(): ?\Illuminate\Contracts\Support\Htmlable
    {
        $status = $this->record->status ?? MaintenanceStatus::SUBMITTED;
        $statusLabel = e($status->getLabel());

        $quoteBadge = '';
        if (!$this->record->is_direct_vendor) {
            $quote = $this->record->currentClientQuote ?? $this->record->clientQuotes()->where('status', '!=', 'archived')->latest()->first();
            if ($quote) {
                if ($quote->status === 'approved') {
                    $quoteBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/60 dark:text-green-200">✅ Quotation Approved</span>';
                } elseif ($quote->status === 'rejected') {
                    $quoteBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/60 dark:text-red-200">❌ Quotation Rejected</span>';
                } else {
                    $quoteBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200 animate-pulse">⏳ Quotation Approval Pending</span>';
                }
            } elseif (filled($this->record->payer_type)) {
                $quoteBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-200">📝 Quotation Required</span>';
            }
        } else {
            $quoteBadge = '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300">🛠 Direct Repair Route</span>';
        }

        $lockedBadge = '';
        if ($this->record->isLocked()) {
            $lockedBadge = '<span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200">🔒 Ticket Locked</span>';
        }

        return new HtmlString(
            '<div class="flex items-center gap-2 text-sm text-gray-500 mt-1 flex-wrap">' .
            '<span>Status: <strong class="text-gray-900 dark:text-gray-100">' . $statusLabel . '</strong></span>' .
            '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' .
            $quoteBadge .
            ($lockedBadge ? '<span class="text-gray-300 dark:text-gray-700">&bull;</span>' . $lockedBadge : '') .
            '</div>'
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('openFinancialWorkflow')
                ->label('Open Quotations & Settlement')
                ->icon('heroicon-o-calculator')
                ->color('indigo')
                ->visible(fn () => !$this->record->is_direct_vendor && (bool) ($this->record->currentClientQuote ?? $this->record->clientQuotes()->where('status', '!=', 'archived')->first()))
                ->url(fn () => \App\Filament\Resources\Billing\MaintenanceQuotationResource::getUrl('edit', ['record' => $this->record->currentClientQuote ?? $this->record->clientQuotes()->where('status', '!=', 'archived')->first()]))
                ->openUrlInNewTab(),

            Action::make('createFinancialWorkflow')
                ->label('Prepare Quotation & Settlement')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->visible(fn () => !$this->record->is_direct_vendor && !($this->record->currentClientQuote ?? $this->record->clientQuotes()->where('status', '!=', 'archived')->first()))
                ->disabled(fn () => blank($this->record->payer_type))
                ->tooltip(fn () => blank($this->record->payer_type) ? 'Please select Who Pays? in the form first.' : 'Launch financial quotation job in Billing & Finance')
                ->requiresConfirmation()
                ->modalHeading('Initialize Financial Quotation & Settlement')
                ->modalDescription('This will create a formal quotation & multi-vendor settlement job for this ticket and open the quotation workspace.')
                ->modalIcon('heroicon-o-calculator')
                ->modalSubmitActionLabel('Yes, Prepare Quotation')
                ->action(function () {
                    $quote = \App\Domain\Maintenance\Models\MaintenanceClientQuote::create([
                        'maintenance_request_id' => $this->record->id,
                        'quote_number' => 'QTE-' . date('Y') . '-' . strtoupper(\Illuminate\Support\Str::random(5)),
                        'status' => 'draft',
                        'total_amount' => 0.00,
                        'owner_amount' => 0.00,
                        'tenant_amount' => 0.00,
                        'dwelly_amount' => 0.00,
                    ]);

                    $this->record->update([
                        'current_client_quote_id' => $quote->id,
                        'status' => $this->record->status === MaintenanceStatus::SUBMITTED ? MaintenanceStatus::QUOTED : $this->record->status,
                    ]);

                    Notification::make()
                        ->title('Quotation Job Created')
                        ->body("Created Quotation #{$quote->quote_number} for ticket #{$this->record->ticket_number}.")
                        ->success()
                        ->send();

                    return redirect(\App\Filament\Resources\Billing\MaintenanceQuotationResource::getUrl('edit', ['record' => $quote]));
                }),

            Action::make('startRepair')
                ->label('Authorize & Start Repair')
                ->icon('heroicon-o-play')
                ->color('success')
                ->visible(function () {
                    return in_array($this->record->status, [
                        MaintenanceStatus::SUBMITTED,
                        MaintenanceStatus::VENDOR_ASSIGNED,
                        MaintenanceStatus::QUOTATION_APPROVED,
                    ]);
                })
                ->disabled(function () {
                    if (blank($this->record->payer_type)) {
                        return true;
                    }

                    if ($this->record->is_direct_vendor) {
                        return !in_array($this->record->status, [
                            MaintenanceStatus::SUBMITTED,
                            MaintenanceStatus::VENDOR_ASSIGNED,
                        ]);
                    }

                    // Dwelly-coordinated route: disabled if quotation is not officially approved or work order is not issued
                    $quote = $this->record->currentClientQuote ?? $this->record->clientQuotes()->where('status', '!=', 'archived')->latest()->first();
                    if (!$quote || $quote->status !== 'approved') {
                        return true;
                    }

                    if (empty($quote->awarded_vendor_quote_ids)) {
                        return true;
                    }

                    return false;
                })
                ->tooltip(function () {
                    if (blank($this->record->payer_type)) {
                        return 'Select financial responsibility (Who Pays?) in the form first.';
                    }

                    if (!$this->record->is_direct_vendor) {
                        $quote = $this->record->currentClientQuote ?? $this->record->clientQuotes()->where('status', '!=', 'archived')->latest()->first();
                        if (!$quote) {
                            return 'Quotation required: Prepare and approve quotation before proceeding with repair.';
                        }
                        if ($quote->status !== 'approved') {
                            return 'Quotation Approval Pending: Client must approve pricing before physical repairs can start.';
                        }
                        if (empty($quote->awarded_vendor_quote_ids)) {
                            return 'Work Order Required: Please award winning vendor quote(s) and issue Work Orders in the Quotation page before proceeding with repair.';
                        }
                    }

                    return 'Authorize & commence on-site physical repairs.';
                })
                ->requiresConfirmation()
                ->modalHeading('Authorize & Start On-Site Repairs')
                ->modalDescription(function () {
                    $ticketNumber = $this->record->ticket_number;
                    $payer = $this->record->payer_type?->getLabel() ?? ucfirst((string)$this->record->payer_type);
                    return "Confirm that technicians are authorized to commence on-site repair work for ticket #{$ticketNumber}. Financial responsibility: {$payer}.";
                })
                ->modalIcon('heroicon-o-play')
                ->modalSubmitActionLabel('Yes, Proceed with Repair')
                ->action(function () {
                    if (!$this->record->is_direct_vendor) {
                        $quote = $this->record->currentClientQuote ?? $this->record->clientQuotes()->where('status', '!=', 'archived')->latest()->first();
                        if (!$quote || $quote->status !== 'approved' || empty($quote->awarded_vendor_quote_ids)) {
                            Notification::make()
                                ->title('Work Order Required')
                                ->body('Work order must be awarded to at least one vendor quote in the quotation record before proceeding with repairs.')
                                ->warning()
                                ->persistent()
                                ->send();
                            return;
                        }
                    }

                    $this->record->update([
                        'status' => MaintenanceStatus::IN_PROGRESS,
                    ]);

                    Notification::make()
                        ->title('Repairs In Progress')
                        ->body("Ticket #{$this->record->ticket_number} marked as In Progress.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),

            Action::make('viewAudit')
                ->label(fn () => $this->record->triggeredAudit ? ('View Audit #' . $this->record->triggeredAudit->audit_number) : 'View Verification Audit')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('info')
                ->button()
                ->size('sm')
                ->visible(fn () => filled($this->record->triggered_audit_id) && (bool)$this->record->triggeredAudit)
                ->url(fn () => \App\Filament\Resources\Operations\AuditResource::getUrl('inspect', ['record' => $this->record->triggeredAudit]))
                ->openUrlInNewTab(),

            Action::make('closeTicket')
                ->label('Close Ticket')
                ->icon('heroicon-o-lock-closed')
                ->color('gray')
                ->visible(fn () => !in_array($this->record->status, [MaintenanceStatus::CLOSED, MaintenanceStatus::CANCELLED]))
                ->requiresConfirmation()
                ->modalHeading('Close Maintenance Ticket')
                ->modalDescription('Confirm that on-site repairs are verified, client acceptance is recorded, and the ticket is ready to be closed.')
                ->action(function () {
                    $record = $this->getRecord();
                    $audit = $record->triggeredAudit;

                    // If an optional audit was initiated, ensure it is approved before closing
                    if ($audit && (!in_array($audit->status?->value ?? (string)$audit->status, ['approved', 'completed']) && !$audit->is_locked)) {
                        Notification::make()
                            ->title('Audit Verification Incomplete')
                            ->body('The linked post-repair verification audit is currently in progress. Please approve or complete the audit before closing.')
                            ->warning()
                            ->persistent()
                            ->send();
                        return;
                    }

                    // Permanently lock the audit if present
                    if ($audit && !$audit->is_locked) {
                        $audit->update([
                            'status' => \App\Domain\Audit\Enums\AuditStatus::APPROVED,
                            'is_locked' => true,
                            'locked_at' => now(),
                            'locked_by_id' => auth()->id(),
                        ]);
                    }

                    $record->update([
                        'status' => MaintenanceStatus::CLOSED,
                        'resolved_at' => $record->resolved_at ?? now(),
                        'completed_at' => $record->completed_at ?? now(),
                    ]);

                    $auditMsg = $audit ? " and Verification Audit #{$audit->audit_number} is locked." : ".";

                    Notification::make()
                        ->title('Ticket Closed')
                        ->body("Maintenance ticket #{$record->ticket_number} has been closed successfully{$auditMsg}")
                        ->success()
                        ->send();

                    $this->refreshFormData(['status']);
                }),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Ticket Overview';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-information-circle';
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $record->update($data);
        return $record;
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
