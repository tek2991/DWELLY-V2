<?php

namespace App\Domain\Finance\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Services\AccountingProvisioningService;
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
     * Generate a Rent Invoice (Tek2991\Accounting\Models\Invoice) for a Tenancy Agreement.
     */
    public function generateRentInvoice(
        TenancyAgreement $agreement,
        int $month,
        int $year,
        array $overrides = []
    ): Invoice {
        return DB::transaction(function () use ($agreement, $month, $year, $overrides) {
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
                $ownerParty = \App\Domain\Party\Models\Party::whereHas('roles', fn ($q) => $q->where('name', 'owner'))
                    ->orWhereHas('ownerProfile')
                    ->first();
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

            $invoice = Invoice::create([
                'branch_id' => $branchId,
                'contact_id' => $tenantContact->id,
                'invoice_number' => $invoiceNumber,
                'status' => InvoiceStatus::Draft,
                'issue_date' => $issueDate,
                'due_date' => $dueDate,
                'currency_code' => 'INR',
                'reference_type' => TenancyAgreement::class,
                'reference_id' => $agreement->id,
                'notes' => $overrides['notes'] ?? "Monthly Rent Demand for {$monthName} - Agreement: {$agreement->code}",
                'terms' => 'Payment due by 5th of every month.',
            ]);

            $rentAmount = (float) ($overrides['rent_amount'] ?? $agreement->rent_amount);
            $utilityAmount = (float) ($overrides['utility_amount'] ?? 0);
            $maintenanceAmount = (float) ($overrides['maintenance_amount'] ?? 0);

            // Add Rent Line Item (Pass-through: Credits Owner AP Liability)
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'line_type' => DocumentLineType::Account,
                'sort_order' => 1,
                'description' => "Rent for {$monthName} ({$agreement->property?->building_name}) [Owner Pass-Through]",
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
     * Bulk generate rent invoices for all active tenancies.
     */
    public function bulkGenerateRentInvoices(int $month, int $year): int
    {
        $agreements = TenancyAgreement::where('status', 'active')->get();
        $count = 0;

        foreach ($agreements as $agreement) {
            $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
            $exists = Invoice::where('reference_type', TenancyAgreement::class)
                ->where('reference_id', $agreement->id)
                ->where('notes', 'like', "%{$monthName}%")
                ->exists();

            if (!$exists) {
                $this->generateRentInvoice($agreement, $month, $year);
                $count++;
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
