<?php

namespace App\Domain\Finance\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Services\AccountingProvisioningService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\InvoiceItem;
use Tek2991\Accounting\Models\Payment;
use Tek2991\Accounting\Services\DocumentNumberService;
use Tek2991\Accounting\Services\InvoiceService;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Enums\DocumentLineType;
use App\Models\User;

class RentBillingService
{
    public function __construct(
        protected AccountingProvisioningService $provisioningService,
        protected InvoiceService $invoiceService,
        protected DocumentNumberService $docNumberService
    ) {}

    /**
     * Get pending, unpaid maintenance invoices for a tenancy agreement (tenant recovery).
     */
    public function getPendingMaintenanceOptions(TenancyAgreement $agreement): array
    {
        $tenantParty = $agreement->tenantParty ?? $agreement->party;
        $tenantContactId = $tenantParty?->accountingContact?->id;

        $maintRequests = \App\Domain\Maintenance\Models\MaintenanceRequest::where('property_id', $agreement->property_id)
            ->where(function ($q) use ($tenantParty) {
                if ($tenantParty) {
                    $q->where('tenant_id', $tenantParty->id)
                      ->orWhere('tenant_amount', '>', 0);
                } else {
                    $q->where('tenant_amount', '>', 0);
                }
            })
            ->pluck('id');

        $query = Invoice::where('reference_type', \App\Domain\Maintenance\Models\MaintenanceRequest::class)
            ->whereIn('reference_id', $maintRequests)
            ->whereNotIn('status', [
                InvoiceStatus::Paid,
                InvoiceStatus::Cancelled,
            ])
            ->where('balance_due', '>', 0);

        if ($tenantContactId) {
            $query->orWhere(function ($q) use ($tenantContactId, $maintRequests) {
                $q->where('contact_id', $tenantContactId)
                  ->whereIn('reference_id', $maintRequests)
                  ->whereNotIn('status', [
                      InvoiceStatus::Paid,
                      InvoiceStatus::Cancelled,
                  ])
                  ->where('balance_due', '>', 0);
            });
        }

        return $query->get()->filter(function (Invoice $mInv) {
            $req = \App\Domain\Maintenance\Models\MaintenanceRequest::find($mInv->reference_id);
            // Exclude if it is exclusively an owner invoice
            if ($req && $req->owner_invoice_id == $mInv->id && $req->tenant_invoice_id != $mInv->id) {
                return false;
            }
            return (float) ($mInv->balance_due > 0 ? $mInv->balance_due : $mInv->grand_total) > 0;
        })->map(function (Invoice $mInv) {
            $req = \App\Domain\Maintenance\Models\MaintenanceRequest::find($mInv->reference_id);
            $amt = (float) ($mInv->balance_due > 0 ? $mInv->balance_due : $mInv->grand_total);

            return [
                'id' => $mInv->id,
                'invoice_number' => $mInv->invoice_number,
                'ticket_number' => $req?->ticket_number ?? 'TKT-' . substr($mInv->id, -4),
                'title' => $req?->title ?? 'Maintenance Work',
                'amount' => $amt,
            ];
        })->values()->toArray();
    }

    /**
     * Calculate billing period, proration status, rent amount, and tenant maintenance add-ons.
     */
    public function calculateBillingDetails(TenancyAgreement $agreement, int $month, int $year, array|null $options = null): array
    {
        if (is_array($options) && !empty($options) && array_is_list($options)) {
            $options = ['selected_maintenance_invoice_ids' => $options];
        } else {
            $options = (array) $options;
        }

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $totalDaysInMonth = (int) $monthStart->daysInMonth;

        $draftInvoice = Invoice::where('reference_type', TenancyAgreement::class)
            ->where('reference_id', $agreement->id)
            ->where(function ($q) use ($monthStart) {
                $q->whereMonth('billing_period_start', $monthStart->month)
                  ->whereYear('billing_period_start', $monthStart->year);
            })
            ->where('status', InvoiceStatus::Draft)
            ->first();

        $draftSnapshot = $draftInvoice?->document_snapshot ?? [];
        $isAdjusted = !empty($options) || ($draftInvoice !== null);

        $handoverDate = $agreement->keys_handed_over_at 
            ? Carbon::parse($agreement->keys_handed_over_at)->startOfDay() 
            : ($agreement->start_date ? Carbon::parse($agreement->start_date)->startOfDay() : null);

        $agreementEnd = $agreement->vacating_date 
            ? Carbon::parse($agreement->vacating_date)->startOfDay() 
            : ($agreement->end_date ? Carbon::parse($agreement->end_date)->startOfDay() : null);

        // 1. Check eligibility
        if ($handoverDate && $handoverDate->gt($monthEnd)) {
            return [
                'eligible' => false,
                'reason' => "Key handover date ({$handoverDate->format('d M Y')}) is after {$monthStart->format('F Y')}.",
                'handover_date' => $handoverDate->toDateString(),
                'handover_date_formatted' => $handoverDate->format('d M Y'),
                'billing_period_start' => null,
                'billing_period_end' => null,
                'formatted_period' => 'Not commenced',
                'is_first_month' => false,
                'is_prorated' => false,
                'days_active' => 0,
                'total_days_in_month' => $totalDaysInMonth,
                'rent_amount' => 0.0,
                'standard_rent' => (float) ($agreement->rent_amount ?? 0),
                'utility_amount' => 0.0,
                'maintenance_amount' => 0.0,
                'maintenance_invoices' => [],
                'maintenance_invoice_ids' => [],
                'total_amount' => 0.0,
                'is_adjusted' => false,
                'draft_invoice_id' => null,
                'notes' => null,
            ];
        }

        if ($agreementEnd && $agreementEnd->lt($monthStart)) {
            return [
                'eligible' => false,
                'reason' => "Agreement ended on {$agreementEnd->format('d M Y')}.",
                'handover_date' => $handoverDate?->toDateString(),
                'handover_date_formatted' => $handoverDate?->format('d M Y') ?? 'N/A',
                'billing_period_start' => null,
                'billing_period_end' => null,
                'formatted_period' => 'Agreement Ended',
                'is_first_month' => false,
                'is_prorated' => false,
                'days_active' => 0,
                'total_days_in_month' => $totalDaysInMonth,
                'rent_amount' => 0.0,
                'standard_rent' => (float) ($agreement->rent_amount ?? 0),
                'utility_amount' => 0.0,
                'maintenance_amount' => 0.0,
                'maintenance_invoices' => [],
                'maintenance_invoice_ids' => [],
                'total_amount' => 0.0,
                'is_adjusted' => false,
                'draft_invoice_id' => null,
                'notes' => null,
            ];
        }

        // 2. Determine Period Start: Handover date if within this month, else 1st of month
        $isFirstMonth = false;
        if ($handoverDate && $handoverDate->format('Y-m') === $monthStart->format('Y-m')) {
            $periodStart = $handoverDate->copy();
            $isFirstMonth = true;
        } else {
            $periodStart = $monthStart->copy();
        }

        // 3. Determine Period End: Agreement end date if ending within this month, else end of month
        if ($agreementEnd && $agreementEnd->lt($monthEnd)) {
            $periodEnd = $agreementEnd->copy();
        } else {
            $periodEnd = $monthEnd->copy();
        }

        // 4. Calculate Days Active and Prorated Rent
        $isProrated = false;
        $standardRent = (float) ($agreement->rent_amount ?? 0);

        if ($isFirstMonth && $periodStart->day > 1) {
            $isProrated = true;
            $daysActive = $totalDaysInMonth - $periodStart->day + 1;

            if ($agreement->first_month_rent !== null && (float) $agreement->first_month_rent > 0) {
                $calculatedRent = (float) $agreement->first_month_rent;
            } else {
                $calculatedRent = round(($standardRent / $totalDaysInMonth) * $daysActive, 2);
            }
        } elseif ($periodEnd->lt($monthEnd)) {
            $isProrated = true;
            $daysActive = (int) $periodEnd->day - (int) $periodStart->day + 1;
            $calculatedRent = round(($standardRent / $totalDaysInMonth) * $daysActive, 2);
        } else {
            $isProrated = false;
            $daysActive = $totalDaysInMonth;
            $calculatedRent = $standardRent;
        }

        $rentAmount = isset($options['rent_amount'])
            ? (float) $options['rent_amount']
            : ($draftSnapshot['custom_inputs']['rent_amount'] ?? ($draftInvoice ? (float) $draftInvoice->subtotal : $calculatedRent));

        $utilityAmount = isset($options['utility_amount'])
            ? (float) $options['utility_amount']
            : ($draftSnapshot['custom_inputs']['utility_amount'] ?? 0.0);

        // 5. Tenant-Payable Maintenance Invoices
        $pendingMaintenance = $this->getPendingMaintenanceOptions($agreement);
        $selectedMaintIds = $options['selected_maintenance_invoice_ids']
            ?? $draftSnapshot['selected_maintenance_invoice_ids']
            ?? null;

        $maintenanceItems = [];
        $maintenanceAmount = 0.0;

        foreach ($pendingMaintenance as $mItem) {
            if ($selectedMaintIds === null || in_array($mItem['id'], $selectedMaintIds)) {
                $maintenanceItems[] = $mItem;
                $maintenanceAmount += (float) $mItem['amount'];
            }
        }

        $totalAmount = round($rentAmount + $utilityAmount + $maintenanceAmount, 2);

        return [
            'eligible' => true,
            'reason' => null,
            'handover_date' => $handoverDate?->toDateString(),
            'handover_date_formatted' => $handoverDate?->format('d M Y') ?? 'N/A',
            'billing_period_start' => $periodStart->toDateString(),
            'billing_period_end' => $periodEnd->toDateString(),
            'formatted_period' => $periodStart->format('d M Y') . ' – ' . $periodEnd->format('d M Y'),
            'is_first_month' => $isFirstMonth,
            'is_prorated' => $isProrated,
            'days_active' => $daysActive,
            'total_days_in_month' => $totalDaysInMonth,
            'rent_amount' => $rentAmount,
            'standard_rent' => $standardRent,
            'utility_amount' => $utilityAmount,
            'maintenance_amount' => $maintenanceAmount,
            'maintenance_invoices' => $maintenanceItems,
            'maintenance_invoice_ids' => array_column($maintenanceItems, 'id'),
            'selected_maintenance_invoice_ids' => $selectedMaintIds !== null ? array_values($selectedMaintIds) : array_column($maintenanceItems, 'id'),
            'total_amount' => $totalAmount,
            'is_adjusted' => $isAdjusted,
            'draft_invoice_id' => $draftInvoice?->id,
            'notes' => $options['notes'] ?? $draftInvoice?->notes,
        ];
    }

    /**
     * Get bulk generation preview details for all active tenancies.
     */
    public function getBulkGenerationPreview(int $month, int $year, string|int|null $propertyId = null): array
    {
        $agreements = TenancyAgreement::where('status', 'active')
            ->when($propertyId, fn ($query) => $query->where('property_id', (string) $propertyId))
            ->with(['property.owner', 'roles.party'])
            ->get();

        $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $items = [];
        $readyCount = 0;
        $alreadyGeneratedCount = 0;
        $ineligibleCount = 0;
        $totalReadyAmount = 0.0;
        $totalBaseRent = 0.0;
        $totalMaintenanceAmount = 0.0;

        foreach ($agreements as $agreement) {
            $details = $this->calculateBillingDetails($agreement, $month, $year);

            $primaryRole = $agreement->roles->where('is_primary', true)->first() ?? $agreement->roles->first();
            $tenantParty = $primaryRole?->party ?? $agreement->party;
            $tenantName = $tenantParty?->display_name ?? 'Tenant';
            $propertyName = $agreement->property?->building_name ?? $agreement->property?->name ?? 'Property';
            $propertyCode = $agreement->property?->code;

            // Check if already billed
            $existingInvoice = null;
            if ($details['eligible']) {
                $existingInvoice = Invoice::where('reference_type', TenancyAgreement::class)
                    ->where('reference_id', $agreement->id)
                    ->where(function ($q) use ($monthName, $details) {
                        $q->where('notes', 'like', "%{$monthName}%")
                          ->orWhere(function ($sub) use ($details) {
                              if (!empty($details['billing_period_start'])) {
                                  $sub->whereDate('billing_period_start', $details['billing_period_start']);
                              }
                          });
                    })
                    ->where('status', '!=', InvoiceStatus::Draft)
                    ->first();
            }

            if (! $details['eligible']) {
                $status = 'ineligible';
                $statusLabel = 'Skipped: ' . $details['reason'];
                $badgeColor = 'gray';
                $ineligibleCount++;
            } elseif ($existingInvoice) {
                $status = 'already_generated';
                $statusLabel = "Already Generated (#{$existingInvoice->invoice_number})";
                $badgeColor = 'info';
                $alreadyGeneratedCount++;
            } else {
                $status = 'ready';
                $statusLabel = $details['is_adjusted'] ? 'Ready • Adjusted' : 'Ready to Generate';
                $badgeColor = $details['is_adjusted'] ? 'amber' : 'success';
                $readyCount++;
                $totalReadyAmount += $details['total_amount'];
                $totalBaseRent += $details['rent_amount'];
                $totalMaintenanceAmount += $details['maintenance_amount'];
            }

            $items[] = array_merge($details, [
                'agreement_id' => $agreement->id,
                'agreement_code' => $agreement->code,
                'tenant_name' => $tenantName,
                'property_id' => $agreement->property_id,
                'property_name' => $propertyName,
                'property_code' => $propertyCode,
                'owner_name' => $agreement->property?->owner?->display_name ?? 'Property Owner',
                'agreement_url' => \App\Filament\Resources\TenancyAgreements\TenancyAgreementResource::getUrl('edit', ['record' => $agreement->id]),
                'property_url' => $agreement->property_id ? \App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $agreement->property_id]) : null,
                'status' => $status,
                'status_label' => $statusLabel,
                'badge_color' => $badgeColor,
                'existing_invoice_number' => $existingInvoice?->invoice_number,
                'existing_invoice_id' => $existingInvoice?->id,
            ]);
        }

        return [
            'month' => $month,
            'year' => $year,
            'month_name' => $monthName,
            'summary' => [
                'total_agreements' => $agreements->count(),
                'ready_count' => $readyCount,
                'already_generated_count' => $alreadyGeneratedCount,
                'ineligible_count' => $ineligibleCount,
                'total_base_rent' => $totalBaseRent,
                'total_maintenance_amount' => $totalMaintenanceAmount,
                'total_ready_amount' => $totalReadyAmount,
            ],
            'items' => $items,
        ];
    }

    /**
     * Generate a Rent Demand (Tek2991\Accounting\Models\Invoice) for a Tenancy Agreement.
     */
    public function generateRentDemand(
        TenancyAgreement $agreement,
        int $month,
        int $year,
        array $overrides = []
    ): Invoice {
        return DB::transaction(function () use ($agreement, $month, $year, $overrides) {
            // Calculate billing period and default / staged amounts
            $calc = $this->calculateBillingDetails($agreement, $month, $year, $overrides);
            if (! $calc['eligible']) {
                throw new \InvalidArgumentException($calc['reason'] ?? "Agreement {$agreement->code} is not eligible for rent demand.");
            }

            $billingPeriodStart = $overrides['billing_period_start'] ?? $calc['billing_period_start'] ?? now()->startOfMonth()->toDateString();
            $billingPeriodEnd = $overrides['billing_period_end'] ?? $calc['billing_period_end'] ?? now()->endOfMonth()->toDateString();
            $formattedPeriod = Carbon::parse($billingPeriodStart)->format('d M Y') . ' – ' . Carbon::parse($billingPeriodEnd)->format('d M Y');

            // 1. Find primary tenant party
            $primaryRole = $agreement->roles()->where('is_primary', true)->first() ?? $agreement->roles()->first();
            $tenantParty = $primaryRole?->party ?? $agreement->tenantParty ?? $agreement->party;
            if (!$tenantParty) {
                throw new \InvalidArgumentException("No valid tenant party linked to agreement {$agreement->code}");
            }

            $this->provisioningService->ensurePartyAccountingReady($tenantParty);
            $tenantContact = $tenantParty->accountingContact ?? $this->provisioningService->ensureAccountingContact($tenantParty);

            // 2. Find property owner party for pass-through rent liability
            $ownerParty = $agreement->property?->owner;
            if (!$ownerParty && $agreement->property_id) {
                $mouPartyId = \App\Domain\Mou\Models\Mou::where('property_id', $agreement->property_id)
                    ->whereNotNull('party_id')
                    ->latest()
                    ->value('party_id');
                if ($mouPartyId) {
                    $ownerParty = \App\Domain\Party\Models\Party::find($mouPartyId);
                }
            }
            if (!$ownerParty) {
                $ownerParty = \App\Domain\Party\Models\Party::whereHas('ownerProfile')->first();
            }

            $ownerPayableAccount = $ownerParty 
                ? $this->provisioningService->getOwnerPayableAccount($ownerParty)
                : (Account::where('system_role', \Tek2991\Accounting\Enums\SystemRole::OwnerPayable)->first()
                    ?? Account::where('type', 'liability')->first());

            $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
            $issueDate = $overrides['issue_date'] ?? now()->toDateString();
            $dueDate = $overrides['due_date'] ?? now()->startOfMonth()->addDays(5)->toDateString();

            $monthStart = Carbon::create($year, $month, 1)->startOfDay();
            $draftInvoice = null;
            if (!empty($overrides['draft_invoice_id'])) {
                $draftInvoice = Invoice::find($overrides['draft_invoice_id']);
            }
            if (!$draftInvoice) {
                $draftInvoice = Invoice::where('reference_type', TenancyAgreement::class)
                    ->where('reference_id', $agreement->id)
                    ->where(function ($q) use ($monthStart) {
                        $q->whereMonth('billing_period_start', $monthStart->month)
                          ->whereYear('billing_period_start', $monthStart->year);
                    })
                    ->where('status', InvoiceStatus::Draft)
                    ->first();
            }

            $notes = $overrides['notes'] ?? $draftInvoice?->notes ?? "Monthly Rent Demand for {$monthName} (Billing Period: {$formattedPeriod}) - Agreement: {$agreement->code}";

            if ($draftInvoice) {
                $invoice = $draftInvoice;
                if (str_starts_with($invoice->invoice_number, 'DRAFT-')) {
                    $invoice->invoice_number = $this->docNumberService->nextInvoiceNumber();
                }
                $invoice->issue_date = $issueDate;
                $invoice->due_date = $dueDate;
                $invoice->billing_period_start = $billingPeriodStart;
                $invoice->billing_period_end = $billingPeriodEnd;
                $invoice->notes = $notes;
                $invoice->save();

                // Clear previous draft items to repopulate cleanly
                $invoice->items()->delete();
            } else {
                $invoiceNumber = $this->docNumberService->nextInvoiceNumber();
                $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId() 
                    ?? $tenantContact->branch_id 
                    ?? $agreement->property?->branch_id 
                    ?? \App\Models\Branch::first()?->id;

                $invoice = Invoice::create([
                    'branch_id' => $branchId,
                    'contact_id' => $tenantContact->id,
                    'invoice_number' => $invoiceNumber,
                    'status' => InvoiceStatus::Draft,
                    'issue_date' => $issueDate,
                    'due_date' => $dueDate,
                    'billing_period_start' => $billingPeriodStart,
                    'billing_period_end' => $billingPeriodEnd,
                    'currency_code' => 'INR',
                    'reference_type' => TenancyAgreement::class,
                    'reference_id' => $agreement->id,
                    'notes' => $notes,
                    'terms' => 'Payment due by 5th of every month.',
                ]);
            }

            $rentAmount = (float) ($overrides['rent_amount'] ?? $calc['rent_amount'] ?? $agreement->rent_amount);
            $utilityAmount = (float) ($overrides['utility_amount'] ?? $calc['utility_amount'] ?? 0);
            $maintenanceAmount = (float) ($overrides['maintenance_amount'] ?? $calc['maintenance_amount'] ?? 0);

            $prorationNote = ($calc['is_prorated'] ?? false) ? " [Prorated - {$calc['days_active']} days]" : "";
            $propertyName = $agreement->property?->building_name ?? $agreement->property?->name ?? 'Property';

            // Add Rent Line Item (Pass-through: Credits Owner AP Liability)
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'line_type' => DocumentLineType::Account,
                'sort_order' => 1,
                'description' => "Rent for {$formattedPeriod} ({$propertyName}){$prorationNote} [Owner Pass-Through]",
                'quantity' => 1,
                'unit_price' => $rentAmount,
                'line_total' => $rentAmount,
                'gross_amount' => $rentAmount,
                'net_amount' => $rentAmount,
                'income_account_id' => $ownerPayableAccount?->id,
            ]);

            if ($utilityAmount > 0) {
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'line_type' => DocumentLineType::Account,
                    'sort_order' => 2,
                    'description' => "Utility Charges - {$monthName}",
                    'quantity' => 1,
                    'unit_price' => $utilityAmount,
                    'line_total' => $utilityAmount,
                    'gross_amount' => $utilityAmount,
                    'net_amount' => $utilityAmount,
                    'income_account_id' => $ownerPayableAccount?->id,
                ]);
            }

            // Add Tenant-payable Maintenance Invoices (Itemized & Settled)
            $selectedMaintIds = $overrides['selected_maintenance_invoice_ids'] ?? $calc['maintenance_invoice_ids'] ?? [];
            if (!empty($selectedMaintIds)) {
                $maintInvoices = Invoice::whereIn('id', $selectedMaintIds)->get();
                $maintIncomeAccount = $this->provisioningService->getMaintenanceIncomeAccount() 
                    ?? Account::where('name', 'like', '%Maintenance%')->first();

                $lineOrder = 3;
                foreach ($maintInvoices as $mInv) {
                    $req = \App\Domain\Maintenance\Models\MaintenanceRequest::find($mInv->reference_id);
                    $mAmount = (float) ($mInv->balance_due > 0 ? $mInv->balance_due : $mInv->grand_total);
                    $ticketNo = $req?->ticket_number ?? 'TKT-' . substr($mInv->id, -4);
                    $title = $req?->title ?? 'Maintenance Work';

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'line_type' => DocumentLineType::Account,
                        'sort_order' => $lineOrder++,
                        'description' => "Maintenance Recovery (#{$ticketNo}: {$title})",
                        'quantity' => 1,
                        'unit_price' => $mAmount,
                        'line_total' => $mAmount,
                        'gross_amount' => $mAmount,
                        'net_amount' => $mAmount,
                        'income_account_id' => $maintIncomeAccount?->id ?? $ownerPayableAccount?->id,
                    ]);

                    // Tag underlying maintenance invoice as consolidated (remains open until Rent Demand payment is recorded)
                    $consolidationNote = "Consolidated into Rent Demand #{$invoice->invoice_number}";
                    if (!str_contains($mInv->notes ?? '', $consolidationNote)) {
                        $mInv->notes = trim(($mInv->notes ? $mInv->notes . "\n" : '') . $consolidationNote);
                        $mInv->save();
                    }
                }
            } elseif ($maintenanceAmount > 0) {
                $maintIncomeAccount = $this->provisioningService->getMaintenanceIncomeAccount();
                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'line_type' => DocumentLineType::Account,
                    'sort_order' => 3,
                    'description' => "Maintenance Fee - {$monthName}",
                    'quantity' => 1,
                    'unit_price' => $maintenanceAmount,
                    'line_total' => $maintenanceAmount,
                    'gross_amount' => $maintenanceAmount,
                    'net_amount' => $maintenanceAmount,
                    'income_account_id' => $maintIncomeAccount?->id ?? $ownerPayableAccount?->id,
                ]);
            }

            // Record linked maintenance invoice IDs in snapshot for synchronized payment settlement
            $snapshot = $invoice->document_snapshot ?: [];
            $snapshot['linked_maintenance_invoice_ids'] = array_values(array_map('intval', $selectedMaintIds));
            $invoice->document_snapshot = $snapshot;
            $invoice->saveQuietly();

            $this->invoiceService->recalculateTotals($invoice);

            // Post to double-entry ledger (DR: Tenant AR, CR: Owner AP)
            $this->invoiceService->post($invoice);
            $invoice->refresh();

            // Compile document snapshot & render immutable stored PDF
            $this->generateAndStoreDemandPdf($invoice);

            return $invoice;
        });
    }

    /**
     * @deprecated Use generateRentDemand() instead. Backward-compatible alias.
     */
    public function generateRentInvoice(TenancyAgreement $agreement, int $month, int $year, array $overrides = []): Invoice
    {
        return $this->generateRentDemand($agreement, $month, $year, $overrides);
    }

    /**
     * Bulk generate rent demands for all eligible active tenancies or selected tenancies.
     */
    public function bulkGenerateRentDemands(
        int $month,
        int $year,
        ?array $selectedAgreementIds = null,
        string|int|null $propertyId = null,
        array $options = []
    ): int {
        $summary = $this->bulkGenerateRentDemandsWithSummary($month, $year, $selectedAgreementIds, $propertyId, $options);
        return $summary['count'];
    }

    /**
     * @deprecated Use bulkGenerateRentDemands() instead. Backward-compatible alias.
     */
    public function bulkGenerateRentInvoices(
        int $month,
        int $year,
        ?array $selectedAgreementIds = null,
        string|int|null $propertyId = null,
        array $options = []
    ): int {
        return $this->bulkGenerateRentDemands($month, $year, $selectedAgreementIds, $propertyId, $options);
    }

    /**
     * Bulk generate rent demands and return a detailed summary.
     */
    public function bulkGenerateRentDemandsWithSummary(
        int $month,
        int $year,
        ?array $selectedAgreementIds = null,
        string|int|null $propertyId = null,
        array $options = []
    ): array {
        $preview = $this->getBulkGenerationPreview($month, $year, $propertyId);
        $generated = [];
        $errors = [];
        $totalAmount = 0.0;

        // Normalise selectedAgreementIds to strings for exact comparison (supports ULID and int IDs)
        $selectedIds = $selectedAgreementIds !== null ? array_map('strval', $selectedAgreementIds) : null;

        foreach ($preview['items'] as $item) {
            if ($item['status'] === 'ready') {
                if ($selectedIds !== null && !in_array((string) $item['agreement_id'], $selectedIds, true)) {
                    continue;
                }

                $agreement = TenancyAgreement::find($item['agreement_id']);
                if ($agreement) {
                    try {
                        $overrides = array_merge([
                            'billing_period_start' => $item['billing_period_start'],
                            'billing_period_end' => $item['billing_period_end'],
                            'rent_amount' => $item['rent_amount'],
                            'utility_amount' => $item['utility_amount'],
                            'maintenance_amount' => $item['maintenance_amount'],
                            'selected_maintenance_invoice_ids' => $item['selected_maintenance_invoice_ids'] ?? $item['maintenance_invoice_ids'] ?? [],
                            'draft_invoice_id' => $item['draft_invoice_id'] ?? null,
                            'notes' => $item['notes'] ?? null,
                        ], $options);

                        $invoice = $this->generateRentDemand($agreement, $month, $year, $overrides);
                        $generated[] = [
                            'invoice_id' => $invoice->id,
                            'invoice_number' => $invoice->invoice_number,
                            'agreement_code' => $agreement->code,
                            'tenant_name' => $item['tenant_name'],
                            'property_name' => $item['property_name'],
                            'total_amount' => (float) $item['total_amount'],
                        ];
                        $totalAmount += (float) $item['total_amount'];
                    } catch (\Throwable $e) {
                        $errors[] = [
                            'agreement_code' => $agreement->code,
                            'error' => $e->getMessage(),
                        ];
                    }
                }
            }
        }

        return [
            'count' => count($generated),
            'generated_invoices' => $generated,
            'total_amount' => $totalAmount,
            'errors' => $errors,
        ];
    }

    public function bulkGenerateRentInvoicesWithSummary(
        int $month,
        int $year,
        ?array $selectedAgreementIds = null,
        string|int|null $propertyId = null,
        array $options = []
    ): array {
        return $this->bulkGenerateRentDemandsWithSummary($month, $year, $selectedAgreementIds, $propertyId, $options);
    }

    /**
     * Record payment against a Rent Demand.
     */
    public function recordPayment(Invoice $invoice, float $amount, ?int $bankAccountId = null, ?string $paymentDate = null, ?string $reference = null, ?string $notes = null): Payment
    {
        if (!$bankAccountId) {
            $bankAccountId = Account::where('type', 'asset')
                ->whereIn('system_role', [\Tek2991\Accounting\Enums\SystemRole::Bank, \Tek2991\Accounting\Enums\SystemRole::Cash])
                ->value('id') ?? Account::where('type', 'asset')->value('id');
        }

        $paymentData = [
            'amount' => $amount,
            'payment_account_id' => $bankAccountId,
            'payment_date' => $paymentDate ?? now()->toDateString(),
            'reference' => $reference,
            'notes' => $notes,
        ];

        $payment = $this->invoiceService->recordPayment($invoice, $paymentData);
        $invoice->refresh();

        // Check if there are linked maintenance invoices to settle
        $linkedMaintIds = $invoice->document_snapshot['linked_maintenance_invoice_ids'] ?? [];

        // Fallback: check invoice line items for ticket numbers if snapshot array is empty
        if (empty($linkedMaintIds)) {
            foreach ($invoice->items as $item) {
                if (preg_match('/#([A-Za-z0-9\-]+):/', $item->description, $m)) {
                    $tkt = $m[1];
                    $req = \App\Domain\Maintenance\Models\MaintenanceRequest::where('ticket_number', $tkt)->first();
                    if ($req && $req->tenant_invoice_id) {
                        $linkedMaintIds[] = (int) $req->tenant_invoice_id;
                    }
                }
            }
        }

        if (!empty($linkedMaintIds)) {
            foreach ($linkedMaintIds as $mInvId) {
                $mInv = Invoice::find($mInvId);
                // Idempotency check: if already paid or zero balance, skip!
                if (!$mInv || $mInv->status === InvoiceStatus::Paid || (float) $mInv->balance_due <= 0) {
                    continue;
                }

                // If demand is fully settled or payment amount covers this maintenance invoice, settle it
                if ($invoice->status === InvoiceStatus::Paid || (float) $invoice->balance_due <= 0 || $amount >= (float) $mInv->grand_total) {
                    $mInv->status = InvoiceStatus::Paid;
                    $mInv->amount_paid = $mInv->grand_total;
                    $mInv->balance_due = 0.00;
                    $settleNote = "Settled via Rent Demand #{$invoice->invoice_number} Payment [Ref: " . ($reference ?: $payment->payment_number) . "]";
                    $mInv->notes = trim(($mInv->notes ? $mInv->notes . "\n" : '') . $settleNote);
                    $mInv->save();
                }
            }
        }

        return $payment;
    }

    /**
     * Compile comprehensive Monthly Rent Demand Notice & Statement data for PDF rendering.
     */
    public function getMonthlyDemandNoticeData(Invoice $invoice): array
    {
        $invoice->loadMissing(['items', 'contact', 'payments', 'branch']);

        $agreement = null;
        if ($invoice->reference_type === TenancyAgreement::class && $invoice->reference_id) {
            $agreement = TenancyAgreement::with(['property.owner', 'primaryTenant.party', 'roles.party'])->find($invoice->reference_id);
        }

        $property = $agreement?->property;
        $owner = $property?->owner;
        if (!$owner && $property?->id) {
            $mouPartyId = \App\Domain\Mou\Models\Mou::where('property_id', $property->id)
                ->whereNotNull('party_id')
                ->latest()
                ->value('party_id');
            if ($mouPartyId) {
                $owner = \App\Domain\Party\Models\Party::find($mouPartyId);
            }
        }
        if (!$owner) {
            $owner = \App\Domain\Party\Models\Party::whereHas('ownerProfile')->first();
        }

        $primaryRole = $agreement?->roles?->where('is_primary', true)->first() ?? $agreement?->roles?->first();
        $tenant = $primaryRole?->party ?? $agreement?->primaryTenant?->party ?? $agreement?->tenantParty ?? $agreement?->party;

        // Calculate previous ledger dues before this demand's issue date
        $previousBalance = 0.0;
        if ($agreement) {
            $previousBalance = (float) Invoice::where('reference_type', TenancyAgreement::class)
                ->where('reference_id', $agreement->id)
                ->where('id', '!=', $invoice->id)
                ->where('issue_date', '<', $invoice->issue_date ?? now())
                ->where('status', '!=', InvoiceStatus::Cancelled)
                ->sum('balance_due');
        }

        return [
            'invoice' => $invoice,
            'agreement' => $agreement,
            'property' => $property,
            'owner' => $owner,
            'tenant' => $tenant,
            'previous_balance' => $previousBalance,
            'current_demand' => (float) $invoice->grand_total,
            'amount_paid' => (float) $invoice->amount_paid,
            'balance_due' => (float) $invoice->balance_due,
            'total_payable' => (float) $invoice->balance_due + $previousBalance,
        ];
    }

    /**
     * Compile comprehensive point-in-time document snapshot payload for immutability.
     */
    public function compileDocumentSnapshot(Invoice $invoice): array
    {
        $noticeData = $this->getMonthlyDemandNoticeData($invoice);

        $agreement = $noticeData['agreement'];
        $property = $noticeData['property'];
        $owner = $noticeData['owner'];
        $tenant = $noticeData['tenant'];
        $branch = $invoice->branch ?? \App\Models\Branch::first();

        $items = $invoice->items->map(function ($item) {
            return [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'line_total' => (float) $item->line_total,
                'gross_amount' => (float) $item->gross_amount,
            ];
        })->toArray();

        return [
            'document_type' => 'rent_demand_notice',
            'version' => '1.0',
            'snapshot_created_at' => now()->toIso8601String(),
            'demand_number' => $invoice->invoice_number,
            'issue_date' => $invoice->issue_date?->toDateString(),
            'due_date' => $invoice->due_date?->toDateString(),
            'billing_period_start' => $invoice->billing_period_start?->toDateString(),
            'billing_period_end' => $invoice->billing_period_end?->toDateString(),
            'billing_period_formatted' => $invoice->billing_period_formatted,
            'currency_code' => $invoice->currency_code ?? 'INR',
            'agreement' => [
                'id' => $agreement?->id,
                'code' => $agreement?->code,
                'start_date' => $agreement?->start_date?->toDateString(),
                'end_date' => $agreement?->end_date?->toDateString(),
                'rent_amount' => (float) ($agreement?->rent_amount ?? 0),
            ],
            'tenant' => [
                'id' => $tenant?->id,
                'display_name' => $tenant?->display_name ?? $invoice->contact?->name ?? 'Tenant',
                'email' => $tenant?->email ?? $invoice->contact?->email,
                'phone' => $tenant?->phone ?? $invoice->contact?->phone,
                'pan_number' => $tenant?->individual?->pan_number,
                'address' => $invoice->contact?->billing_address ?? $tenant?->address,
            ],
            'owner' => [
                'id' => $owner?->id,
                'display_name' => $owner?->display_name ?? $owner?->name ?? 'Property Owner',
                'email' => $owner?->email,
                'phone' => $owner?->phone,
                'pan_number' => $owner?->individual?->pan_number,
            ],
            'property' => [
                'id' => $property?->id,
                'code' => $property?->code,
                'building_name' => $property?->building_name ?? $property?->name,
                'unit_number' => $property?->unit_number ?? $property?->flat_number,
                'address_line_1' => $property?->address_line_1,
                'address_line_2' => $property?->address_line_2,
                'city' => $property?->city,
                'pincode' => $property?->pincode,
            ],
            'company' => [
                'name' => 'Dwelly Living Private Limited',
                'legal_name' => 'Dwelly Living Private Limited',
                'branch_name' => $branch?->name ?? 'Headquarters',
                'support_email' => 'finance@dwelly.in',
                'support_phone' => '+91 98765 43210',
                'website' => 'https://dwelly.in',
                'bank_name' => 'HDFC Bank Ltd',
                'account_name' => 'Dwelly Living Pvt Ltd - Escrow / Client Holding A/C',
                'account_number' => '50200012345678',
                'ifsc_code' => 'HDFC0001234',
                'upi_id' => 'dwelly.holding@hdfcbank',
            ],
            'items' => $items,
            'current_demand' => (float) $invoice->grand_total,
            'previous_balance' => (float) ($noticeData['previous_balance'] ?? 0.0),
            'amount_paid' => (float) $invoice->amount_paid,
            'balance_due' => (float) $invoice->balance_due,
            'total_payable' => (float) ($noticeData['total_payable'] ?? $invoice->balance_due),
            'linked_maintenance_invoice_ids' => $invoice->document_snapshot['linked_maintenance_invoice_ids'] ?? [],
            'notes' => $invoice->notes,
            'terms' => $invoice->terms,
        ];
    }

    /**
     * Generate, store immutable PDF file, and update invoice metadata with snapshot and SHA-256 checksum.
     */
    public function generateAndStoreDemandPdf(Invoice $invoice, bool $force = false): string
    {
        $disk = Storage::disk('local');

        if (!$force && !empty($invoice->pdf_path) && $disk->exists($invoice->pdf_path)) {
            return $disk->path($invoice->pdf_path);
        }

        // 1. Ensure snapshot is compiled and persisted
        $snapshot = array_merge($this->compileDocumentSnapshot($invoice), $invoice->document_snapshot ?: []);
        $noticeData = $this->getMonthlyDemandNoticeData($invoice);

        // 2. Render PDF
        $pdf = Pdf::loadView('pdf.rent_demand_notice', [
            'invoice' => $invoice,
            'noticeData' => $noticeData,
            'snapshot' => $snapshot,
        ]);

        $pdfOutput = $pdf->output();
        $checksum = hash('sha256', $pdfOutput);

        // 3. Store to immutable directory structure: documents/rent_demands/{year}/{month}/demand_{invoice_number}.pdf
        $year = $invoice->billing_period_start ? $invoice->billing_period_start->format('Y') : date('Y');
        $month = $invoice->billing_period_start ? $invoice->billing_period_start->format('m') : date('m');
        $filename = "demand_{$invoice->invoice_number}.pdf";
        $relativePath = "documents/rent_demands/{$year}/{$month}/{$filename}";

        $disk->put($relativePath, $pdfOutput);

        // 4. Update database record with snapshot, storage path, generation timestamp, and checksum
        $invoice->updateQuietly([
            'document_snapshot' => $snapshot,
            'pdf_path' => $relativePath,
            'pdf_generated_at' => now(),
            'pdf_checksum' => $checksum,
        ]);

        return $disk->path($relativePath);
    }

    /**
     * Save or update a draft rent demand invoice with custom adjustments.
     */
    public function saveDraftRentDemand(TenancyAgreement $agreement, int $month, int $year, array $adjustedData, ?User $actor = null): Invoice
    {
        return DB::transaction(function () use ($agreement, $month, $year, $adjustedData, $actor) {
            $details = $this->calculateBillingDetails($agreement, $month, $year, $adjustedData);
            if (! $details['eligible']) {
                throw new \InvalidArgumentException($details['reason'] ?? 'Agreement is not eligible for rent demand.');
            }

            $monthStart = Carbon::create($year, $month, 1)->startOfDay();
            $periodStart = $details['billing_period_start'] ?? $monthStart->toDateString();
            $periodEnd = $details['billing_period_end'] ?? $monthStart->copy()->endOfMonth()->toDateString();
            $formattedPeriod = Carbon::parse($periodStart)->format('d M Y') . ' – ' . Carbon::parse($periodEnd)->format('d M Y');

            $primaryRole = $agreement->roles()->where('is_primary', true)->first() ?? $agreement->roles()->first();
            $tenantParty = $primaryRole?->party ?? $agreement->tenantParty ?? $agreement->party;
            if (!$tenantParty) {
                throw new \InvalidArgumentException("No valid tenant party linked to agreement {$agreement->code}");
            }

            $this->provisioningService->ensurePartyAccountingReady($tenantParty);
            $tenantContact = $tenantParty->accountingContact ?? $this->provisioningService->ensureAccountingContact($tenantParty);

            $ownerParty = $agreement->property?->owner;
            if (!$ownerParty && $agreement->property_id) {
                $mouPartyId = \App\Domain\Mou\Models\Mou::where('property_id', $agreement->property_id)
                    ->whereNotNull('party_id')
                    ->latest()
                    ->value('party_id');
                if ($mouPartyId) {
                    $ownerParty = \App\Domain\Party\Models\Party::find($mouPartyId);
                }
            }
            if (!$ownerParty) {
                $ownerParty = \App\Domain\Party\Models\Party::whereHas('ownerProfile')->first();
            }

            $ownerPayableAccount = $ownerParty 
                ? $this->provisioningService->getOwnerPayableAccount($ownerParty)
                : (Account::where('system_role', \Tek2991\Accounting\Enums\SystemRole::OwnerPayable)->first()
                    ?? Account::where('type', 'liability')->first());

            $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId() 
                ?? $tenantContact->branch_id 
                ?? $agreement->property?->branch_id 
                ?? \App\Models\Branch::first()?->id;

            $draftInvoice = Invoice::where('reference_type', TenancyAgreement::class)
                ->where('reference_id', $agreement->id)
                ->where(function ($q) use ($monthStart) {
                    $q->whereMonth('billing_period_start', $monthStart->month)
                      ->whereYear('billing_period_start', $monthStart->year);
                })
                ->where('status', InvoiceStatus::Draft)
                ->first();

            $cleanCode = substr(preg_replace('/[^A-Za-z0-9]/', '', $agreement->code), 0, 12);
            $draftNumber = "DRAFT-{$cleanCode}-{$year}{$month}";

            if (! $draftInvoice) {
                $draftInvoice = new Invoice();
                $draftInvoice->branch_id = $branchId;
                $draftInvoice->contact_id = $tenantContact->id;
                $draftInvoice->invoice_number = $draftNumber;
                $draftInvoice->status = InvoiceStatus::Draft;
                $draftInvoice->currency_code = 'INR';
                $draftInvoice->reference_type = TenancyAgreement::class;
                $draftInvoice->reference_id = $agreement->id;
            }

            $snapshot = [
                'is_adjusted' => true,
                'adjusted_by' => $actor?->id,
                'adjusted_at' => now()->toIso8601String(),
                'selected_maintenance_invoice_ids' => $details['selected_maintenance_invoice_ids'] ?? [],
                'custom_inputs' => $adjustedData,
            ];

            $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
            $issueDate = $adjustedData['issue_date'] ?? now()->toDateString();
            $dueDate = $adjustedData['due_date'] ?? now()->startOfMonth()->addDays(5)->toDateString();

            $draftInvoice->issue_date = $issueDate;
            $draftInvoice->due_date = $dueDate;
            $draftInvoice->billing_period_start = $periodStart;
            $draftInvoice->billing_period_end = $periodEnd;
            $draftInvoice->notes = $adjustedData['notes'] ?? $details['notes'] ?? "Monthly Rent Demand for {$monthName} (Billing Period: {$formattedPeriod}) - Agreement: {$agreement->code}";
            $draftInvoice->terms = 'Payment due by 5th of every month.';
            $draftInvoice->document_snapshot = $snapshot;
            $draftInvoice->save();

            // Clear previous items
            $draftInvoice->items()->delete();

            $rentAmount = (float) $details['rent_amount'];
            $utilityAmount = (float) $details['utility_amount'];
            $prorationNote = ($details['is_prorated'] ?? false) ? " [Prorated - {$details['days_active']} days]" : "";
            $propertyName = $agreement->property?->building_name ?? $agreement->property?->name ?? 'Property';

            InvoiceItem::create([
                'invoice_id' => $draftInvoice->id,
                'line_type' => DocumentLineType::Account,
                'sort_order' => 1,
                'description' => "Rent for {$formattedPeriod} ({$propertyName}){$prorationNote} [Owner Pass-Through]",
                'quantity' => 1,
                'unit_price' => $rentAmount,
                'line_total' => $rentAmount,
                'gross_amount' => $rentAmount,
                'net_amount' => $rentAmount,
                'income_account_id' => $ownerPayableAccount?->id,
            ]);

            if ($utilityAmount > 0) {
                InvoiceItem::create([
                    'invoice_id' => $draftInvoice->id,
                    'line_type' => DocumentLineType::Account,
                    'sort_order' => 2,
                    'description' => "Utility Charges - {$monthName}",
                    'quantity' => 1,
                    'unit_price' => $utilityAmount,
                    'line_total' => $utilityAmount,
                    'gross_amount' => $utilityAmount,
                    'net_amount' => $utilityAmount,
                    'income_account_id' => $ownerPayableAccount?->id,
                ]);
            }

            $selectedMaintIds = $details['selected_maintenance_invoice_ids'] ?? [];
            if (!empty($selectedMaintIds)) {
                $maintInvoices = Invoice::whereIn('id', $selectedMaintIds)->get();
                $maintIncomeAccount = $this->provisioningService->getMaintenanceIncomeAccount() 
                    ?? Account::where('name', 'like', '%Maintenance%')->first();

                $lineOrder = 3;
                foreach ($maintInvoices as $mInv) {
                    $req = \App\Domain\Maintenance\Models\MaintenanceRequest::find($mInv->reference_id);
                    $mAmount = (float) ($mInv->balance_due > 0 ? $mInv->balance_due : $mInv->grand_total);
                    $ticketNo = $req?->ticket_number ?? 'TKT-' . substr($mInv->id, -4);
                    $title = $req?->title ?? 'Maintenance Work';

                    InvoiceItem::create([
                        'invoice_id' => $draftInvoice->id,
                        'line_type' => DocumentLineType::Account,
                        'sort_order' => $lineOrder++,
                        'description' => "Maintenance Recovery (#{$ticketNo}: {$title})",
                        'quantity' => 1,
                        'unit_price' => $mAmount,
                        'line_total' => $mAmount,
                        'gross_amount' => $mAmount,
                        'net_amount' => $mAmount,
                        'income_account_id' => $maintIncomeAccount?->id ?? $ownerPayableAccount?->id,
                    ]);
                }
            }

            $this->invoiceService->recalculateTotals($draftInvoice);

            return $draftInvoice;
        });
    }

    /**
     * Delete draft rent demand invoice for a tenancy agreement, reverting to calculated defaults.
     */
    public function resetDraftRentDemand(TenancyAgreement $agreement, int $month, int $year): bool
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();

        $draftInvoice = Invoice::where('reference_type', TenancyAgreement::class)
            ->where('reference_id', $agreement->id)
            ->where(function ($q) use ($monthStart) {
                $q->whereMonth('billing_period_start', $monthStart->month)
                  ->whereYear('billing_period_start', $monthStart->year);
            })
            ->where('status', InvoiceStatus::Draft)
            ->first();

        if ($draftInvoice) {
            $draftInvoice->items()->delete();
            return (bool) $draftInvoice->delete();
        }

        return false;
    }
}


