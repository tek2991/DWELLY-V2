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
            ->whereIn('status', [
                InvoiceStatus::Draft,
                InvoiceStatus::Sent,
                InvoiceStatus::PartiallyPaid,
            ]);

        if ($tenantContactId) {
            $query->orWhere(function ($q) use ($tenantContactId, $maintRequests) {
                $q->where('contact_id', $tenantContactId)
                  ->whereIn('reference_id', $maintRequests)
                  ->whereIn('status', [
                      InvoiceStatus::Draft,
                      InvoiceStatus::Sent,
                      InvoiceStatus::PartiallyPaid,
                  ]);
            });
        }

        return $query->get()->map(function (Invoice $mInv) {
            $req = \App\Domain\Maintenance\Models\MaintenanceRequest::find($mInv->reference_id);
            $amt = (float) ($mInv->balance_due > 0 ? $mInv->balance_due : $mInv->grand_total);

            return [
                'id' => $mInv->id,
                'invoice_number' => $mInv->invoice_number,
                'ticket_number' => $req?->ticket_number ?? 'TKT-' . substr($mInv->id, -4),
                'title' => $req?->title ?? 'Maintenance Work',
                'amount' => $amt,
            ];
        })->toArray();
    }

    /**
     * Calculate billing period, proration status, rent amount, and tenant maintenance add-ons.
     */
    public function calculateBillingDetails(TenancyAgreement $agreement, int $month, int $year, ?array $selectedMaintenanceInvoiceIds = null): array
    {
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth()->startOfDay();
        $totalDaysInMonth = (int) $monthStart->daysInMonth;

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
                $rentAmount = (float) $agreement->first_month_rent;
            } else {
                $rentAmount = round(($standardRent / $totalDaysInMonth) * $daysActive, 2);
            }
        } elseif ($periodEnd->lt($monthEnd)) {
            $isProrated = true;
            $daysActive = (int) $periodEnd->day - (int) $periodStart->day + 1;
            $rentAmount = round(($standardRent / $totalDaysInMonth) * $daysActive, 2);
        } else {
            $isProrated = false;
            $daysActive = $totalDaysInMonth;
            $rentAmount = $standardRent;
        }

        // 5. Tenant-Payable Maintenance Invoices
        $pendingMaintenance = $this->getPendingMaintenanceOptions($agreement);
        $maintenanceItems = [];
        $maintenanceAmount = 0.0;

        foreach ($pendingMaintenance as $mItem) {
            if ($selectedMaintenanceInvoiceIds === null || in_array($mItem['id'], $selectedMaintenanceInvoiceIds)) {
                $maintenanceItems[] = $mItem;
                $maintenanceAmount += (float) $mItem['amount'];
            }
        }

        $totalAmount = round($rentAmount + $maintenanceAmount, 2);

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
            'utility_amount' => 0.0,
            'maintenance_amount' => $maintenanceAmount,
            'maintenance_invoices' => $maintenanceItems,
            'maintenance_invoice_ids' => array_column($maintenanceItems, 'id'),
            'total_amount' => $totalAmount,
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
                $statusLabel = 'Ready to Generate';
                $badgeColor = 'success';
                $readyCount++;
                $totalReadyAmount += $details['total_amount'];
                $totalBaseRent += $details['rent_amount'];
                $totalMaintenanceAmount += $details['maintenance_amount'];
            }

            $items[] = [
                'agreement_id' => $agreement->id,
                'agreement_code' => $agreement->code,
                'tenant_name' => $tenantName,
                'property_id' => $agreement->property_id,
                'property_name' => $propertyName,
                'property_code' => $propertyCode,
                'owner_name' => $agreement->property?->owner?->display_name ?? 'Property Owner',
                'agreement_url' => \App\Filament\Resources\TenancyAgreements\TenancyAgreementResource::getUrl('edit', ['record' => $agreement->id]),
                'property_url' => $agreement->property_id ? \App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $agreement->property_id]) : null,
                'handover_date_formatted' => $details['handover_date_formatted'],
                'billing_period_start' => $details['billing_period_start'],
                'billing_period_end' => $details['billing_period_end'],
                'formatted_period' => $details['formatted_period'],
                'is_first_month' => $details['is_first_month'],
                'is_prorated' => $details['is_prorated'],
                'days_active' => $details['days_active'],
                'total_days_in_month' => $details['total_days_in_month'],
                'rent_amount' => $details['rent_amount'],
                'standard_rent' => $details['standard_rent'],
                'utility_amount' => $details['utility_amount'],
                'maintenance_amount' => $details['maintenance_amount'],
                'maintenance_invoices' => $details['maintenance_invoices'],
                'maintenance_invoice_ids' => $details['maintenance_invoice_ids'],
                'total_amount' => $details['total_amount'],
                'status' => $status,
                'status_label' => $statusLabel,
                'badge_color' => $badgeColor,
                'existing_invoice_number' => $existingInvoice?->invoice_number,
                'existing_invoice_id' => $existingInvoice?->id,
            ];
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
            // Calculate billing period and default prorated amounts
            $calc = $this->calculateBillingDetails($agreement, $month, $year);

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

            $invoiceNumber = $this->docNumberService->nextInvoiceNumber();
            $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId() 
                ?? $tenantContact->branch_id 
                ?? $agreement->property?->branch_id 
                ?? \App\Models\Branch::first()?->id;

            $notes = $overrides['notes'] ?? "Monthly Rent Demand for {$monthName} (Billing Period: {$formattedPeriod}) - Agreement: {$agreement->code}";

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

            $rentAmount = (float) ($overrides['rent_amount'] ?? $calc['rent_amount'] ?? $agreement->rent_amount);
            $utilityAmount = (float) ($overrides['utility_amount'] ?? 0);
            $maintenanceAmount = (float) ($overrides['maintenance_amount'] ?? 0);

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

                    // Settle underlying maintenance invoice to prevent double-billing
                    $mInv->update([
                        'status' => InvoiceStatus::Paid,
                        'paid_amount' => $mInv->grand_total,
                        'balance_due' => 0.0,
                        'notes' => trim(($mInv->notes ?? '') . " | Consolidated into Rent Demand #{$invoice->invoice_number}"),
                    ]);
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
     * Backward-compatible alias for generateRentDemand.
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
                            'selected_maintenance_invoice_ids' => $item['maintenance_invoice_ids'] ?? [],
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

        return $this->invoiceService->recordPayment($invoice, $paymentData);
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
        $snapshot = $invoice->document_snapshot ?: $this->compileDocumentSnapshot($invoice);
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
}


