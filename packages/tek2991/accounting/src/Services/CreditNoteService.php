<?php

namespace Tek2991\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Models\CreditNote;
use Tek2991\Accounting\Enums\CreditNoteStatus;
use Exception;

class CreditNoteService
{
    public function __construct(
        private TransactionService $txnService,
        private DocumentNumberService $docNumberService,
        private PostingGuard $postingGuard,
    ) {}

    public function create(int $companyId, array $data): CreditNote
    {
        return DB::transaction(function () use ($companyId, $data) {
            $cn = new CreditNote($data);
            $cn->company_id = $companyId;
            $cn->credit_note_number = $this->docNumberService->nextCreditNoteNumber($companyId);
            $cn->status = CreditNoteStatus::Draft;
            $cn->save();

            return $cn;
        });
    }

    public function createFromInvoice(\Tek2991\Accounting\Models\Invoice $invoice, array $quantitiesToReturn): CreditNote
    {
        return DB::transaction(function () use ($invoice, $quantitiesToReturn) {
            $cn = new CreditNote([
                'contact_id' => $invoice->contact_id,
                'invoice_id' => $invoice->id,
                'issue_date' => now(),
            ]);
            $cn->company_id = $invoice->company_id;
            $cn->credit_note_number = $this->docNumberService->nextCreditNoteNumber($invoice->company_id);
            $cn->status = CreditNoteStatus::Draft;
            $cn->save();

            foreach ($invoice->items as $origItem) {
                $returnQty = $quantitiesToReturn[$origItem->id] ?? 0;
                if ($returnQty > 0) {
                    $cn->items()->create([
                        'item_id' => $origItem->item_id,
                        'sort_order' => $origItem->sort_order,
                        'description' => $origItem->description,
                        'hsn_sac_code' => $origItem->hsn_sac_code,
                        'quantity' => $returnQty,
                        'unit_price' => $origItem->unit_price,
                        'tax_id' => $origItem->tax_id,
                    ]);
                }
            }

            $this->recalculateTotals($cn);

            return $cn;
        });
    }

    public function recalculateTotals(CreditNote $cn): void
    {
        $subtotal = 0;
        $taxTotal = 0;

        $isLinkedToInvoice = $cn->invoice_id !== null;
        if ($isLinkedToInvoice) {
            $cn->loadMissing('invoice.items');
            $invoiceItems = $cn->invoice->items->keyBy('item_id');
        }

        foreach ($cn->items as $item) {
            $qty = ($item->getAttributes()['quantity'] ?? 0);
            
            if ($isLinkedToInvoice && isset($invoiceItems[$item->item_id])) {
                $origItem = $invoiceItems[$item->item_id];
                $origQty = $origItem->getAttributes()['quantity'] ?? 1;
                if ($origQty == 0) $origQty = 1;
                
                $proportion = $qty / $origQty;

                $origGross = $origItem->getAttributes()['gross_amount'] ?? 0;
                $origLineDiscount = $origItem->getAttributes()['line_discount_amount'] ?? 0;
                $origAllocDiscount = $origItem->getAttributes()['allocated_document_discount'] ?? 0;
                $origNetAmount = $origItem->getAttributes()['net_amount'] ?? 0;
                $origLineTotal = $origItem->getAttributes()['line_total'] ?? 0;
                $origTaxAmount = $origItem->getAttributes()['tax_amount'] ?? 0;

                $item->gross_amount = round($origGross * $proportion) / 100;
                $item->line_discount_amount = round($origLineDiscount * $proportion) / 100;
                $item->allocated_document_discount = round($origAllocDiscount * $proportion) / 100;
                $item->net_amount = round($origNetAmount * $proportion) / 100;
                $item->line_total = round($origLineTotal * $proportion) / 100;
                $item->tax_amount = round($origTaxAmount * $proportion) / 100;

                $scaledSnapshot = [];
                if ($origItem->tax_snapshot) {
                    foreach ($origItem->tax_snapshot as $taxComp) {
                        $compAmount = round($taxComp['amount'] * $proportion);
                        $scaledSnapshot[] = [
                            'account_id' => $taxComp['account_id'],
                            'amount' => $compAmount,
                        ];
                    }
                }
                $item->tax_snapshot = $scaledSnapshot;
                $item->save();

                $subtotal += ($item->getAttributes()['line_total'] ?? 0);
                $taxTotal += ($item->getAttributes()['tax_amount'] ?? 0);
            } else {
                if (empty($qty) || $qty == 0) {
                    $qty = 1;
                }
                $baseLineTotal = $qty * ($item->getAttributes()['unit_price'] ?? 0);
                
                $itemTaxAmount = 0;
                $isInclusive = false;
                
                if ($item->tax_id) {
                    $tax = \Tek2991\Accounting\Models\Tax::with('components')->find($item->tax_id);
                    if ($tax) {
                        $isInclusive = $tax->type === \Tek2991\Accounting\Enums\TaxType::Inclusive;
                        
                        $cn->loadMissing('contact');
                        $companyProfile = \Tek2991\Accounting\Models\CompanyProfile::firstOrCreate(
                            ['company_id' => $cn->company_id],
                            ['tax_regime' => \Tek2991\Accounting\Enums\TaxRegimeType::Generic]
                        );

                        $taxContext = new \Tek2991\Accounting\ValueObjects\TaxCalculationContext(
                            amount: $baseLineTotal,
                            document: $cn,
                            tax: $tax,
                            modeOverride: $isInclusive ? 'inclusive' : 'exclusive',
                            companyProfile: $companyProfile,
                            contact: $cn->contact
                        );

                        $taxComponents = app(\Tek2991\Accounting\Services\TaxService::class)->calculateTax($taxContext);
                        $itemTaxAmount = $taxComponents->sum('amount');
                        $item->tax_snapshot = $taxComponents->toArray();
                    }
                }
                
                $itemPreTaxTotal = $isInclusive ? ($baseLineTotal - $itemTaxAmount) : $baseLineTotal;
                
                $item->gross_amount = $baseLineTotal / 100;
                $item->line_discount_amount = 0;
                $item->allocated_document_discount = 0;
                $item->net_amount = $baseLineTotal / 100;
                
                $item->line_total = $itemPreTaxTotal / 100;
                $item->tax_amount = $itemTaxAmount / 100;
                
                $item->save();

                $subtotal += $itemPreTaxTotal;
                $taxTotal += $itemTaxAmount;
            }
        }

        $cn->subtotal = $subtotal / 100;
        $cn->tax_total = $taxTotal / 100;
        
        $grandTotal = $subtotal + $taxTotal;
        $cn->grand_total = $grandTotal / 100;
        
        $balanceRemaining = $grandTotal - ($cn->getAttributes()['applied_amount'] ?? 0);
        $cn->balance_remaining = $balanceRemaining / 100;

        $cn->save();
    }

    public function post(CreditNote $cn): void
    {
        DB::transaction(function () use ($cn) {
            $cn = CreditNote::lockForUpdate()->find($cn->id);
            
            if ($cn->transaction_id !== null) {
                return;
            }
            
            if ($cn->status !== CreditNoteStatus::Draft) {
                throw new Exception("Only draft credit notes can be posted.");
            }

            $receivableAccountId = $cn->contact->receivableAccount?->id ?? \Tek2991\Accounting\Models\Account::where('company_id', $cn->company_id)
                ->where('system_role', \Tek2991\Accounting\Enums\SystemRole::TradeReceivable)
                ->value('id');

            if (!$receivableAccountId) {
                throw new \Exception("Cannot post credit note: No receivable account found for customer and no default Accounts Receivable exists.");
            }

            $entries = [];

            // CR: Receivable Account
            $entries[] = [
                'account_id' => $receivableAccountId,
                'type' => 'credit',
                'amount' => $cn->grand_total,
                'description' => "Credit Note {$cn->credit_note_number}",
            ];

            // DR: Income Accounts & Taxes (reversal)
            $incomeAccounts = [];
            $taxAccounts = [];

            foreach ($cn->items as $item) {
                $incAccountId = null;
                if ($item->line_type === \Tek2991\Accounting\Enums\DocumentLineType::Item) {
                    $incAccountId = $item->item?->income_account_id;
                } elseif ($item->line_type === \Tek2991\Accounting\Enums\DocumentLineType::Account) {
                    $incAccountId = $item->income_account_id;
                }
                
                if (!$incAccountId) {
                    throw new Exception("Missing income account for item.");
                }

                if (!isset($incomeAccounts[$incAccountId])) {
                    $incomeAccounts[$incAccountId] = 0;
                }
                $incomeAccounts[$incAccountId] += $item->line_total;

                if ($item->tax_snapshot) {
                    foreach ($item->tax_snapshot as $taxComp) {
                        $taxAccId = $taxComp['account_id'];
                        if (!isset($taxAccounts[$taxAccId])) {
                            $taxAccounts[$taxAccId] = 0;
                        }
                        $taxAccounts[$taxAccId] += ($taxComp['amount'] / 100);
                    }
                }
            }

            foreach ($incomeAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'type' => 'debit',
                        'amount' => $amount,
                        'description' => "Credit Note {$cn->credit_note_number} Revenue Reversal",
                    ];
                }
            }

            foreach ($taxAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'type' => 'debit',
                        'amount' => $amount,
                        'description' => "Credit Note {$cn->credit_note_number} Tax Reversal",
                    ];
                }
            }

            $transaction = $this->txnService->createTransaction([
                'company_id' => $cn->company_id,
                'type' => \Tek2991\Accounting\Enums\TransactionType::CreditNote,
                'description' => "Posted Credit Note {$cn->credit_note_number}",
                'posted_at' => $cn->issue_date?->toDateString() ?? now()->toDateString(),
                'reference' => $cn->credit_note_number,
            ], $entries);

            $cn->transaction_id = $transaction->id;
            $cn->status = CreditNoteStatus::Issued;
            $cn->save();
            
            activity('financial')
                ->performedOn($cn)
                ->event('credit_note.posted')
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'grand_total' => $cn->grand_total
                ])
                ->log("Credit Note {$cn->credit_note_number} posted");
        });
    }

    public function cancel(CreditNote $cn): void
    {
        DB::transaction(function () use ($cn) {
            $cn = CreditNote::lockForUpdate()->find($cn->id);
            
            if ($cn->status === CreditNoteStatus::Cancelled) {
                return;
            }
            if ($cn->status === CreditNoteStatus::Applied) {
                throw new Exception("Cannot cancel an applied credit note.");
            }
            
            if ($cn->transaction_id) {
                $this->txnService->reverseTransaction($cn->transaction, "Reversal of Cancelled CN: {$cn->credit_note_number}");
            }

            $cn->status = CreditNoteStatus::Cancelled;
            $cn->save();
            
            activity('financial')
                ->performedOn($cn)
                ->event('credit_note.cancelled')
                ->log("Credit Note {$cn->credit_note_number} cancelled");
        });
    }

    public function applyToDocument(CreditNote $cn, \Tek2991\Accounting\Models\Invoice $invoice, int $amount): void
    {
        DB::transaction(function () use ($cn, $invoice, $amount) {
            $cn = CreditNote::lockForUpdate()->find($cn->id);
            $invoice = \Tek2991\Accounting\Models\Invoice::lockForUpdate()->find($invoice->id);
            
            if ($cn->status !== CreditNoteStatus::Issued && $cn->status !== CreditNoteStatus::PartiallyApplied) {
                throw new Exception("Only issued or partially applied credit notes can be applied.");
            }
            if ($cn->contact_id !== $invoice->contact_id) {
                throw new Exception("Credit note and invoice must belong to the same contact.");
            }
            if ($amount > ($cn->getRawOriginal('balance_remaining'))) {
                throw new Exception("Cannot apply more than the remaining balance of the credit note.");
            }
            if ($amount > ($invoice->getRawOriginal('balance_due'))) {
                throw new Exception("Cannot apply more than the remaining balance of the invoice.");
            }

            // DR: Receivable Account (reversing the CN) - wait, CN already credited Receivable. 
            // To apply it against an invoice, we just update balances. 
            // The ledger balances of Receivable are already reduced by the CN. 
            // Applying it simply matches them visually in the subledger (amount_paid / balance_due).
            // So we don't need Journal Entries! The CN already reduced AR.
            // But wait, the Invoice balance_due is just a subledger cache.
            
            // Update CN
            $newApplied = $cn->getRawOriginal('applied_amount') + $amount;
            $cn->applied_amount = $newApplied;
            $cn->balance_remaining = $cn->getRawOriginal('grand_total') - $newApplied;
            if (($cn->getRawOriginal('grand_total') - $newApplied) <= 0) {
                $cn->status = CreditNoteStatus::Applied;
            } else {
                $cn->status = CreditNoteStatus::PartiallyApplied;
            }
            $cn->save();

            // Update Invoice
            $newPaid = $invoice->getRawOriginal('amount_paid') + $amount;
            $invoice->amount_paid = $newPaid;
            $invoice->balance_due = $invoice->getRawOriginal('grand_total') - $newPaid;
            if (($invoice->getRawOriginal('grand_total') - $newPaid) <= 0) {
                $invoice->status = \Tek2991\Accounting\Enums\InvoiceStatus::Paid;
            } else {
                $invoice->status = \Tek2991\Accounting\Enums\InvoiceStatus::PartiallyPaid;
            }
            $invoice->save();

            // Record application in DB (assuming a pivot table exists, but if not we just use Activity log for now)
            activity('financial')
                ->performedOn($cn)
                ->event('credit_note.applied')
                ->withProperties([
                    'invoice_id' => $invoice->id,
                    'amount' => $amount
                ])
                ->log("Applied " . number_format($amount / 100, 2) . " to Invoice {$invoice->invoice_number}");
                
            activity('financial')
                ->performedOn($invoice)
                ->event('invoice.credit_applied')
                ->withProperties([
                    'credit_note_id' => $cn->id,
                    'amount' => $amount
                ])
                ->log("Credit Note {$cn->credit_note_number} applied for " . number_format($amount / 100, 2));
        });
    }
}
