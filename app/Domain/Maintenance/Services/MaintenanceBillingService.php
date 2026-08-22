<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceVendorQuote;
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

            // If owner party is null, fallback to property owner / MOU party
            if (!$party && ($billType === 'owner_invoice' || empty($request->tenant_id))) {
                $party = $request->property?->owner;
                if (!$party && $request->property_id) {
                    $mouPartyId = \App\Domain\Mou\Models\Mou::where('property_id', $request->property_id)
                        ->whereNotNull('party_id')
                        ->latest()
                        ->value('party_id');
                    if ($mouPartyId) {
                        $party = \App\Domain\Party\Models\Party::find($mouPartyId);
                    }
                }
                if ($party && empty($request->owner_id)) {
                    $request->update(['owner_id' => $party->id]);
                }
            }

            // If tenant party is null, fallback to property active lease tenant party
            if (!$party && $billType === 'tenant_invoice') {
                $agreement = $request->property?->agreements()->latest()->first();
                $party = $agreement?->tenantParty ?? $agreement?->party;
                if ($party && empty($request->tenant_id)) {
                    $request->update(['tenant_id' => $party->id]);
                }
            }

            // General fallback: if one is present, use it
            if (!$party) {
                $party = $request->owner ?? $request->tenant;
            }

            // Final fallback: check any party associated with property
            if (!$party && $request->property_id) {
                $anyPartyId = \App\Domain\Mou\Models\Mou::where('property_id', $request->property_id)->whereNotNull('party_id')->latest()->value('party_id')
                    ?? \App\Domain\Agreement\Models\TenancyAgreement::where('property_id', $request->property_id)->whereNotNull('tenant_party_id')->latest()->value('tenant_party_id');
                if ($anyPartyId) {
                    $party = \App\Domain\Party\Models\Party::find($anyPartyId);
                    if ($party) {
                        if ($billType === 'owner_invoice') {
                            $request->update(['owner_id' => $party->id]);
                        } else {
                            $request->update(['tenant_id' => $party->id]);
                        }
                    }
                }
            }

            if (!$party) {
                $propertyName = $request->property?->building_name ?: 'Property';
                throw new \InvalidArgumentException("No valid party (Owner / Tenant) associated with maintenance ticket #{$request->ticket_number} ({$propertyName}) for bill type {$billType}. Please ensure the Owner or Tenant party is assigned on the ticket.");
            }

            $this->provisioningService->ensurePartyAccountingReady($party);
            $party->refresh();
            $contact = $party->accountingContact ?? \Tek2991\Accounting\Models\Contact::where('party_id', $party->id)->first();

            $incomeAccount = Account::where('type', \Tek2991\Accounting\Enums\AccountType::Revenue->value)->first();
            $invoiceNumber = $this->docNumberService->nextInvoiceNumber();
            $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId() ?? $contact->branch_id ?? $request->property?->branch_id ?? \App\Models\Branch::first()?->id;

            $invoice = Invoice::create([
                'branch_id' => $branchId,
                'contact_id' => $contact->id,
                'invoice_number' => $invoiceNumber,
                'reference_type' => MaintenanceRequest::class,
                'reference_id' => $request->id,
                'status' => InvoiceStatus::Sent,
                'issue_date' => $options['issue_date'] ?? now()->toDateString(),
                'due_date' => $options['due_date'] ?? now()->addDays(7)->toDateString(),
                'currency_code' => 'INR',
                'notes' => $options['notes'] ?? "Maintenance Invoice for Ticket {$request->ticket_number}: {$request->title}",
            ]);

            $quote = $request->currentClientQuote ?? $request->clientQuotes()->latest()->first();

            if (empty($lineItems) && $quote && $quote->items()->count() > 0) {
                $isFullAmount = ($billType === 'owner_invoice' && (float)$quote->owner_amount == (float)$quote->total_amount) ||
                                ($billType === 'tenant_invoice' && (float)$quote->tenant_amount == (float)$quote->total_amount);

                if ($isFullAmount) {
                    foreach ($quote->items as $qItem) {
                        $lineItems[] = [
                            'description' => $qItem->description . " (Ticket #{$request->ticket_number})",
                            'quantity' => (float) ($qItem->quantity ?: 1),
                            'unit_price' => (float) $qItem->unit_price,
                            'total' => (float) $qItem->total_price,
                        ];
                    }
                } else {
                    $cost = match ($billType) {
                        'tenant_invoice' => (float)$quote->tenant_amount > 0 ? (float)$quote->tenant_amount : (float)$quote->total_amount,
                        'owner_invoice' => (float)$quote->owner_amount > 0 ? (float)$quote->owner_amount : (float)$quote->total_amount,
                        default => (float)$quote->total_amount,
                    };

                    $lineItems[] = [
                        'description' => "Maintenance Service Share: {$request->title} (Quote #{$quote->quote_number}, Ticket #{$request->ticket_number})",
                        'quantity' => 1,
                        'unit_price' => $cost,
                        'total' => $cost,
                    ];
                }
            } elseif (empty($lineItems)) {
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
     * Create a Vendor Bill for a specific Maintenance Vendor Quote.
     */
    public function createVendorBillForQuote(
        \App\Domain\Maintenance\Models\MaintenanceVendorQuote $vendorQuote,
        array $options = []
    ): Bill {
        return DB::transaction(function () use ($vendorQuote, $options) {
            $vendorParty = $vendorQuote->vendor;
            if (!$vendorParty) {
                throw new \InvalidArgumentException("No vendor party associated with quote #{$vendorQuote->id}");
            }

            $this->provisioningService->ensurePartyAccountingReady($vendorParty);
            $vendorParty->refresh();
            $contact = $vendorParty->accountingContact ?? \Tek2991\Accounting\Models\Contact::where('party_id', $vendorParty->id)->first();

            $expenseAccount = Account::where('type', 'expense')->first();
            $billNumber = $this->docNumberService->nextBillNumber();
            $request = $vendorQuote->maintenanceRequest;
            $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId() ?? $contact->branch_id ?? $request->property?->branch_id ?? \App\Models\Branch::first()?->id;

            $bill = Bill::create([
                'branch_id' => $branchId,
                'contact_id' => $contact->id,
                'bill_number' => $billNumber,
                'vendor_reference' => $vendorQuote->vendor_quote_number ?: $vendorQuote->work_order_number,
                'reference_type' => MaintenanceRequest::class,
                'reference_id' => $request->id,
                'status' => BillStatus::Received,
                'issue_date' => $options['issue_date'] ?? now()->toDateString(),
                'due_date' => $options['due_date'] ?? now()->addDays(14)->toDateString(),
                'currency_code' => 'INR',
                'notes' => $options['notes'] ?? "Vendor Bill for {$vendorQuote->trade_title} (Ticket #{$request->ticket_number}, Work Order #{$vendorQuote->work_order_number})",
            ]);

            $cost = $vendorQuote->final_cost ?? $vendorQuote->quoted_cost;
            $description = $vendorQuote->trade_title ?: "Repair Trade Service";
            if ($vendorQuote->work_order_number) {
                $description .= " [Work Order #{$vendorQuote->work_order_number}]";
            }
            if ($vendorQuote->vendor_quote_number) {
                $description .= " [Quote #{$vendorQuote->vendor_quote_number}]";
            }
            if ($vendorQuote->scope_of_work) {
                $description .= " - " . \Illuminate\Support\Str::limit($vendorQuote->scope_of_work, 80);
            }

            BillItem::create([
                'bill_id' => $bill->id,
                'line_type' => DocumentLineType::Account,
                'sort_order' => 1,
                'description' => $description,
                'quantity' => 1,
                'unit_price' => (float) $cost,
                'line_total' => (float) $cost,
                'gross_amount' => (float) $cost,
                'net_amount' => (float) $cost,
                'expense_account_id' => $expenseAccount?->id,
            ]);

            $this->billService->recalculateTotals($bill);
            $vendorQuote->update([
                'bill_id' => (string) $bill->id,
                'status' => 'billed',
            ]);

            if (empty($request->bill_id)) {
                $request->update(['bill_id' => (string) $bill->id]);
            }

            return $bill;
        });
    }

    /**
     * Create Vendor Bills for all awarded vendor quotes on a maintenance request.
     * @return Bill[]
     */
    public function createAllVendorBillsForRequest(MaintenanceRequest $request, array $options = []): array
    {
        $bills = [];
        $quote = $request->currentClientQuote ?? $request->clientQuotes()->latest()->first();
        $awardedIds = (array) ($quote?->awarded_vendor_quote_ids ?? []);

        $quotesQuery = $request->vendorQuotes()->whereNull('bill_id');
        if (!empty($awardedIds)) {
            $quotes = $quotesQuery->whereIn('id', $awardedIds)->get();
        } elseif ($request->vendorQuotes()->where('is_awarded', true)->exists()) {
            $quotes = $quotesQuery->where('is_awarded', true)->get();
        } else {
            $quotes = $quotesQuery->get();
        }

        foreach ($quotes as $q) {
            $bills[] = $this->createVendorBillForQuote($q, $options);
        }

        // Fallback for single vendor legacy request if no quotes exist but vendor_party_id is set
        if ($quotes->isEmpty() && $request->vendor_party_id && empty($request->bill_id)) {
            $bills[] = $this->createVendorBill($request, [], $options);
        }

        return $bills;
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
            $vendorParty = $request->vendor;
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
                'reference_type' => MaintenanceRequest::class,
                'reference_id' => $request->id,
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

    /**
     * Record client approval on quotation and advance operational maintenance request.
     */
    public function recordClientApproval(MaintenanceClientQuote $quote, array $data): void
    {
        DB::transaction(function () use ($quote, $data) {
            $quote->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by_type' => $data['approved_by_type'] ?? 'owner',
                'approval_channel' => $data['approval_channel'] ?? 'written',
                'approval_notes' => $data['approval_notes'] ?? null,
            ]);

            $request = $quote->maintenanceRequest;
            if ($request) {
                $request->update([
                    'quotation_status' => 'approved',
                    'quotation_approved_at' => now(),
                    'quotation_approval_notes' => $data['approval_notes'] ?? null,
                    'status' => MaintenanceStatus::QUOTATION_APPROVED,
                ]);
                $request->syncQuotationTotals();
            }
        });
    }

    /**
     * Award winning vendor quote(s) and advance maintenance request to Work Orders Issued.
     */
    public function awardVendorQuotesAndIssueWorkOrders(MaintenanceClientQuote $quote, array $selectedIds): void
    {
        DB::transaction(function () use ($quote, $selectedIds) {
            $quote->update([
                'awarded_vendor_quote_ids' => $selectedIds,
            ]);

            $request = $quote->maintenanceRequest;
            $vendorQuotes = MaintenanceVendorQuote::whereIn('id', $selectedIds)->get();
            $year = now()->year;
            $quoteSuffix = strtoupper(substr(str_replace(['QT-', 'QTE-'], '', $quote->quote_number ?: (string) $quote->id), -5));

            foreach ($vendorQuotes as $idx => $vq) {
                if (blank($vq->work_order_number)) {
                    $seq = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
                    $vq->work_order_number = "WO-{$year}-{$quoteSuffix}-{$seq}";
                }

                $vq->work_order_issued_at = $vq->work_order_issued_at ?: now();
                $vq->is_awarded = true;
                $vq->status = 'awarded';
                $vq->save();

                // Automatically generate work order PDF document
                app(MaintenanceWorkOrderPdfService::class)->generatePdf($vq, $quote);
            }

            if ($request) {
                $request->update([
                    'status' => MaintenanceStatus::IN_PROGRESS,
                ]);
            }
        });
    }

    /**
     * Archive active quotation(s) and unlock the maintenance request's financial responsibility.
     * Allowed only before work orders have been issued.
     */
    public function archiveQuotationAndUnlock(MaintenanceRequest $request): void
    {
        $quote = $request->currentClientQuote ?? $request->clientQuotes()->where('status', '!=', 'archived')->latest()->first();

        $isApproved = ($quote && $quote->status === 'approved')
            || $request->isQuotationApproved();

        $hasWorkOrders = ($quote && ! empty($quote->awarded_vendor_quote_ids))
            || $request->vendorQuotes()->where('is_awarded', true)->exists()
            || in_array($request->status, [MaintenanceStatus::IN_PROGRESS, MaintenanceStatus::WORK_COMPLETED, MaintenanceStatus::RESOLVED, MaintenanceStatus::CLOSED]);

        if ($isApproved || $hasWorkOrders) {
            throw new \RuntimeException('Cannot unlock financial responsibility after Quotation has been approved or Work Orders have been issued.');
        }

        DB::transaction(function () use ($request, $quote) {
            if ($quote) {
                $quote->update(['status' => 'archived']);
            }
            $request->clientQuotes()->where('status', '!=', 'archived')->update(['status' => 'archived']);

            $request->update([
                'current_client_quote_id' => null,
                'status' => in_array($request->status, [MaintenanceStatus::QUOTED, MaintenanceStatus::QUOTATION_PENDING, MaintenanceStatus::QUOTATION_APPROVED])
                    ? MaintenanceStatus::SUBMITTED
                    : $request->status,
            ]);
        });
    }
}
