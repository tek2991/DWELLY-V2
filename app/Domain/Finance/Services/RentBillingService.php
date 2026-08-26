<?php

namespace App\Domain\Finance\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Services\AccountingProvisioningService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
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
     * Calculate billing period, proration status, and rent amount for an agreement and billing month/year.
     */
    public function calculateBillingDetails(TenancyAgreement $agreement, int $month, int $year): array
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
            'maintenance_amount' => 0.0,
            'total_amount' => $rentAmount,
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
            }

            $items[] = [
                'agreement_id' => $agreement->id,
                'agreement_code' => $agreement->code,
                'tenant_name' => $tenantName,
                'property_name' => $propertyName,
                'property_code' => $propertyCode,
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
                'total_ready_amount' => $totalReadyAmount,
            ],
            'items' => $items,
        ];
    }

    /**
     * Generate a Rent Invoice (Tek2991\Accounting\Models\Invoice) for a Tenancy Agreement.
     */
    public function generateRentInvoice(
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

            if ($maintenanceAmount > 0) {
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

            return $invoice;
        });
    }

    /**
     * Bulk generate rent invoices for all eligible active tenancies or selected tenancies.
     *
     * @param int $month
     * @param int $year
     * @param array<string|int>|null $selectedAgreementIds
     * @param string|int|null $propertyId
     * @return int
     */
    public function bulkGenerateRentInvoices(int $month, int $year, ?array $selectedAgreementIds = null, string|int|null $propertyId = null): int
    {
        $preview = $this->getBulkGenerationPreview($month, $year, $propertyId);
        $count = 0;

        // Normalise selectedAgreementIds to strings for exact comparison (supports ULID and int IDs)
        $selectedIds = $selectedAgreementIds !== null ? array_map('strval', $selectedAgreementIds) : null;

        foreach ($preview['items'] as $item) {
            if ($item['status'] === 'ready') {
                if ($selectedIds !== null && !in_array((string) $item['agreement_id'], $selectedIds, true)) {
                    continue;
                }

                $agreement = TenancyAgreement::find($item['agreement_id']);
                if ($agreement) {
                    $this->generateRentInvoice($agreement, $month, $year, [
                        'billing_period_start' => $item['billing_period_start'],
                        'billing_period_end' => $item['billing_period_end'],
                        'rent_amount' => $item['rent_amount'],
                        'utility_amount' => $item['utility_amount'],
                        'maintenance_amount' => $item['maintenance_amount'],
                    ]);
                    $count++;
                }
            }
        }

        return $count;
    }

    /**
     * Record payment against a Rent Invoice.
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
}

