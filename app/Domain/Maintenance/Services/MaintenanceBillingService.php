<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Enums\DocumentLineType;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\BillItem;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\InvoiceItem;
use Tek2991\Accounting\Services\BillService;
use Tek2991\Accounting\Services\DocumentNumberService;
use Tek2991\Accounting\Services\InvoiceService;

class MaintenanceBillingService
{
    public function __construct(
        protected AccountingProvisioningService $provisioningService,
        protected InvoiceService $invoiceService,
        protected BillService $billService,
        protected DocumentNumberService $docNumberService
    ) {}

    /**
     * Create an Invoice (to Tenant or Owner) for a Maintenance Request.
     */
    public function createMaintenanceInvoice(
        MaintenanceRequest $request,
        string $billType, // 'tenant_invoice' or 'owner_invoice'
        array $lineItems = [],
        array $options = []
    ): Invoice {
        return DB::transaction(function () use ($request, $billType, $lineItems, $options) {
            $party = match ($billType) {
                'tenant_invoice' => $request->tenant,
                'owner_invoice' => $request->owner,
                default => $request->tenant ?? $request->owner,
            };

            if (!$party) {
                throw new \InvalidArgumentException("No valid party associated with maintenance request for bill type {$billType}");
            }

            $this->provisioningService->ensurePartyAccountingReady($party);
            $contact = $party->accountingContact;

            $incomeAccount = Account::where('type', 'income')->first();
            $invoiceNumber = $this->docNumberService->nextInvoiceNumber();

            $invoice = Invoice::create([
                'contact_id' => $contact->id,
                'invoice_number' => $invoiceNumber,
                'status' => InvoiceStatus::Sent,
                'issue_date' => $options['issue_date'] ?? now()->toDateString(),
                'due_date' => $options['due_date'] ?? now()->addDays(7)->toDateString(),
                'currency_code' => 'INR',
                'reference_type' => MaintenanceRequest::class,
                'reference_id' => $request->id,
                'notes' => $options['notes'] ?? "Maintenance Invoice for Ticket {$request->ticket_number}: {$request->title}",
            ]);

            if (empty($lineItems)) {
                $cost = match ($billType) {
                    'tenant_invoice' => $request->tenant_amount > 0 ? $request->tenant_amount : $request->total_cost,
                    'owner_invoice' => $request->owner_amount > 0 ? $request->owner_amount : $request->total_cost,
                    default => $request->total_cost,
                };

                $lineItems = [
                    [
                        'description' => "Maintenance Service: {$request->title} ({$request->ticket_number})",
                        'quantity' => 1,
                        'unit_price' => (float) $cost,
                        'total' => (float) $cost,
                    ]
                ];
            }

            foreach ($lineItems as $idx => $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['unit_price'] ?? 0);
                $total = (float) ($item['total'] ?? ($qty * $price));

                InvoiceItem::create([
                    'invoice_id' => $invoice->id,
                    'line_type' => DocumentLineType::Account,
                    'sort_order' => $idx + 1,
                    'description' => $item['description'] ?? 'Maintenance Charge',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'line_total' => $total,
                    'gross_amount' => $total,
                    'net_amount' => $total,
                    'income_account_id' => $incomeAccount?->id,
                ]);
            }

            $this->invoiceService->recalculateTotals($invoice);

            if ($billType === 'tenant_invoice') {
                $request->update(['tenant_invoice_id' => (string) $invoice->id]);
            } else {
                $request->update(['owner_invoice_id' => (string) $invoice->id]);
            }

            return $invoice;
        });
    }

    /**
     * Create a Vendor Bill for a Maintenance Request.
     */
    public function createVendorBill(
        MaintenanceRequest $request,
        array $lineItems = [],
        array $options = []
    ): Bill {
        return DB::transaction(function () use ($request, $lineItems, $options) {
            $vendorParty = $request->vendorParty;
            if (!$vendorParty) {
                throw new \InvalidArgumentException("No vendor party associated with maintenance request {$request->ticket_number}");
            }

            $this->provisioningService->ensurePartyAccountingReady($vendorParty);
            $contact = $vendorParty->accountingContact;

            $expenseAccount = Account::where('type', 'expense')->first();
            $billNumber = $this->docNumberService->nextBillNumber();

            $bill = Bill::create([
                'contact_id' => $contact->id,
                'bill_number' => $billNumber,
                'status' => BillStatus::Received,
                'issue_date' => $options['issue_date'] ?? now()->toDateString(),
                'due_date' => $options['due_date'] ?? now()->addDays(14)->toDateString(),
                'currency_code' => 'INR',
                'notes' => $options['notes'] ?? "Vendor Bill for Maintenance Ticket {$request->ticket_number}",
            ]);

            if (empty($lineItems)) {
                $vendorCost = $request->vendor_cost > 0 ? $request->vendor_cost : $request->total_cost;
                $lineItems = [
                    [
                        'description' => "Vendor Work: {$request->title} ({$request->ticket_number})",
                        'quantity' => 1,
                        'unit_price' => (float) $vendorCost,
                        'total' => (float) $vendorCost,
                    ]
                ];
            }

            foreach ($lineItems as $idx => $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $price = (float) ($item['unit_price'] ?? 0);
                $total = (float) ($item['total'] ?? ($qty * $price));

                BillItem::create([
                    'bill_id' => $bill->id,
                    'line_type' => DocumentLineType::Account,
                    'sort_order' => $idx + 1,
                    'description' => $item['description'] ?? 'Vendor Repair Charge',
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'line_total' => $total,
                    'gross_amount' => $total,
                    'net_amount' => $total,
                    'expense_account_id' => $expenseAccount?->id,
                ]);
            }

            $this->billService->recalculateTotals($bill);
            $request->update(['bill_id' => (string) $bill->id]);

            return $bill;
        });
    }
}
