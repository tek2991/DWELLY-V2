<?php

namespace Tek2991\Accounting\Services;

use App\Models\Branch;

use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Payment;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Enums\DiscountType;
use Tek2991\Accounting\Models\JournalEntry;
use Exception;

class InvoiceService
{
    public function __construct(
        private TransactionService $txnService,
        private DocumentNumberService $docNumberService,
        private PostingGuard $postingGuard,
        private PdfService $pdfService,
    ) {}

    public function create(?\App\Models\Branch $branch, array $data): Invoice
    {
        return DB::transaction(function () use ($branch, $data) {
            $invoice = new Invoice($data);
            $invoice->branch_id = $branch?->id;
            $invoice->invoice_number = $this->docNumberService->nextInvoiceNumber($branch);
            $invoice->status = InvoiceStatus::Draft;
            $invoice->save();

            return $invoice;
        });
    }

    public function recalculateTotals(Invoice $invoice): void
    {
        $grossSubtotal = 0;
        $totalItemDiscounts = 0;

        // First pass: Gross amounts and item discounts
        foreach ($invoice->items as $item) {
            $qty = ($item->getAttributes()['quantity'] ?? 0);
            if (empty($qty) || $qty == 0) {
                $qty = 1;
            }
            $price = ($item->getAttributes()['unit_price'] ?? 0);
            $gross = $qty * $price;
            $item->gross_amount = $gross / 100;
            
            $itemDiscount = 0;
            if ($item->discount_type === DiscountType::Percentage) {
                $itemDiscount = $gross * (($item->getAttributes()['discount_rate'] ?? 0) / 100);
            } elseif ($item->discount_type === DiscountType::Fixed) {
                $itemDiscount = ($item->getAttributes()['discount_amount'] ?? 0);
            }
            $item->line_discount_amount = $itemDiscount / 100;
            
            $grossSubtotal += $gross;
            $totalItemDiscounts += $itemDiscount;
        }

        $preDocSubtotal = $grossSubtotal - $totalItemDiscounts;
        $invoice->subtotal = $grossSubtotal / 100;
        
        // Document discount
        $docDiscount = 0;
        if ($invoice->discount_type === DiscountType::Percentage) {
            $docDiscount = $preDocSubtotal * (($invoice->getAttributes()['discount_rate'] ?? 0) / 100);
        } elseif ($invoice->discount_type === DiscountType::Fixed) {
            $docDiscount = ($invoice->getAttributes()['discount_amount'] ?? 0);
        }
        $invoice->discount_amount = $docDiscount / 100;

        // Second pass: Allocation and taxes
        $remainingDocDiscount = $docDiscount;
        $itemsCount = $invoice->items->count();
        $i = 0;
        
        $taxTotal = 0;
        $netItemsTotal = 0;

        foreach ($invoice->items as $item) {
            $i++;
            $lineNetBeforeDoc = (($item->getAttributes()['gross_amount'] ?? 0) - ($item->getAttributes()['line_discount_amount'] ?? 0));
            
            $allocated = 0;
            if ($itemsCount > 0) {
                if ($i === $itemsCount) {
                    $allocated = $remainingDocDiscount;
                } else {
                    $proportion = $preDocSubtotal > 0 ? ($lineNetBeforeDoc / $preDocSubtotal) : 0;
                    $allocated = round($docDiscount * $proportion);
                    $remainingDocDiscount -= $allocated;
                }
            }
            $item->allocated_document_discount = $allocated / 100;
            
            $taxableValue = $lineNetBeforeDoc - $allocated;
            $item->net_amount = $taxableValue / 100;
            
            // Calculate Tax
            $itemTaxAmount = 0;
            $isInclusive = false;
            
            if ($item->tax_id) {
                $tax = \Tek2991\Accounting\Models\Tax::with('components')->find($item->tax_id);
                if ($tax) {
                    $isInclusive = $tax->type === \Tek2991\Accounting\Enums\TaxType::Inclusive;
                    // Invoices don't have a document-level mode, they use the tax type directly
                    $docMode = $isInclusive ? 'inclusive' : 'exclusive';
                    
                    $invoice->loadMissing('contact');
                    $branch = \App\Models\Branch::find($invoice->branch_id);

                    $taxContext = new \Tek2991\Accounting\ValueObjects\TaxCalculationContext(
                        amount: $taxableValue,
                        document: $invoice,
                        tax: $tax,
                        modeOverride: $docMode,
                        branch: $branch,
                        contact: $invoice->contact
                    );

                    $taxComponents = app(\Tek2991\Accounting\Services\TaxService::class)->calculateTax($taxContext);
                    $itemTaxAmount = $taxComponents->sum('amount');
                    $item->tax_snapshot = $taxComponents->toArray();
                }
            }
            
            $itemPreTaxTotal = $isInclusive ? ($taxableValue - $itemTaxAmount) : $taxableValue;
            
            $item->line_total = $itemPreTaxTotal / 100;
            $item->tax_amount = $itemTaxAmount / 100; 
            
            $item->save();

            $netItemsTotal += $itemPreTaxTotal;
            $taxTotal += $itemTaxAmount;
        }

        $invoice->tax_total = $taxTotal / 100;
        
        $grandTotal = $netItemsTotal + $taxTotal;
        $invoice->grand_total = $grandTotal / 100;
        
        $balanceDue = $grandTotal - ($invoice->getAttributes()['amount_paid'] ?? 0);
        $invoice->balance_due = $balanceDue / 100;

        $invoice->save();
    }

    public function post(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice = Invoice::lockForUpdate()->find($invoice->id);
            
            if ($invoice->transaction_id !== null) {
                return; // Idempotent
            }
            
            $this->postingGuard->assertInvoicePostable($invoice);

            // 1. Create Journal Entries
            $receivableAccountId = $invoice->contact->receivableAccount?->id ?? \Tek2991\Accounting\Models\Account::where('system_role', \Tek2991\Accounting\Enums\SystemRole::TradeReceivable)
                ->value('id');

            if (!$receivableAccountId) {
                throw new \Exception("Cannot post invoice: No receivable account found for customer and no default Accounts Receivable exists.");
            }

            $entries = [];

            // DR: Receivable Account
            $entries[] = [
                'account_id' => $receivableAccountId,
                'type' => 'debit',
                'amount' => $invoice->grand_total,
                'description' => "Invoice {$invoice->invoice_number}",
            ];

            // CR: Income Accounts (These are natively reduced by both line and doc discounts)
            $incomeAccounts = [];
            $taxAccounts = [];

            foreach ($invoice->items as $item) {
                $incAccountId = null;
                if ($item->line_type === \Tek2991\Accounting\Enums\DocumentLineType::Item) {
                    $incAccountId = $item->item?->income_account_id;
                } elseif ($item->line_type === \Tek2991\Accounting\Enums\DocumentLineType::Account) {
                    $incAccountId = $item->income_account_id;
                }
                
                if (!$incAccountId) {
                    throw new Exception("Missing income account for invoice line item.");
                }

                if (!isset($incomeAccounts[$incAccountId])) {
                    $incomeAccounts[$incAccountId] = 0;
                }
                $incomeAccounts[$incAccountId] += $item->line_total;

                // Aggregate taxes from snapshot
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
                        'type' => 'credit',
                        'amount' => $amount,
                        'description' => "Invoice {$invoice->invoice_number} Revenue",
                    ];
                }
            }

            foreach ($taxAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'type' => 'credit',
                        'amount' => $amount,
                        'description' => "Invoice {$invoice->invoice_number} Tax",
                    ];
                }
            }

            $transaction = $this->txnService->createTransaction([
                'branch_id' => $invoice->branch_id,
                'posted_at' => $invoice->issue_date,
                'description' => "Posted Invoice {$invoice->invoice_number}",
                'type' => \Tek2991\Accounting\Enums\TransactionType::InvoicePosting,
                'reference' => $invoice->invoice_number,
                'reviewed' => false,
                'pending' => false,
            ], $entries);

            $invoice->transaction_id = $transaction->id;
            $invoice->status = InvoiceStatus::Sent;
            $invoice->save();
            
            if ($invoice->contact_id) {
                $invoice->contact->increment('receivable_balance', $invoice->getRawOriginal('grand_total'));
            }
            
            activity('financial')
                ->performedOn($invoice)
                ->event('invoice.posted')
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'grand_total' => $invoice->grand_total
                ])
                ->log("Invoice {$invoice->invoice_number} posted");
        });
    }

    public function recordPayment(Invoice $invoice, array $paymentData): Payment
    {
        return DB::transaction(function () use ($invoice, $paymentData) {
            $invoice = Invoice::lockForUpdate()->find($invoice->id);
            $this->postingGuard->assertPaymentAllowed($invoice);
            
            $paymentAmount = $paymentData['amount'];

            $receivableAccountId = $invoice->contact->receivableAccount?->id ?? \Tek2991\Accounting\Models\Account::where('system_role', \Tek2991\Accounting\Enums\SystemRole::TradeReceivable)
                ->value('id');

            if (!$receivableAccountId) {
                throw new \Exception("Cannot record payment: No receivable account found for customer and no default Accounts Receivable exists.");
            }

            // DR: Payment Bank Account
            // CR: Receivable Account
            $entries = [
                [
                    'account_id' => $paymentData['payment_account_id'],
                    'type' => 'debit',
                    'amount' => $paymentAmount,
                    'description' => "Payment for Invoice {$invoice->invoice_number}",
                ],
                [
                    'account_id' => $receivableAccountId,
                    'type' => 'credit',
                    'amount' => $paymentAmount,
                    'description' => "Payment for Invoice {$invoice->invoice_number}",
                ],
            ];

            $transaction = $this->txnService->createTransaction([
                'branch_id' => $invoice->branch_id,
                'posted_at' => $paymentData['payment_date'],
                'description' => "Payment against Invoice {$invoice->invoice_number}",
                'type' => \Tek2991\Accounting\Enums\TransactionType::PaymentIn,
                'reference' => "PAY-{$invoice->invoice_number}",
                'reviewed' => false,
                'pending' => false,
            ], $entries);

            $payment = new Payment($paymentData);
            $payment->branch_id = $invoice->branch_id;
            $payment->transaction_id = $transaction->id;
            $invoice->payments()->save($payment);

            // Update invoice amounts
            $newPaid = $invoice->amount_paid + $paymentAmount;
            $newBalance = $invoice->grand_total - $newPaid;

            $invoice->amount_paid = $newPaid;
            $invoice->balance_due = $newBalance;

            if ($newBalance <= 0) {
                $invoice->status = InvoiceStatus::Paid;
            } else {
                $invoice->status = InvoiceStatus::PartiallyPaid;
            }
            
            $invoice->save();
            
            if ($invoice->contact_id) {
                $invoice->contact->decrement('receivable_balance', $paymentAmount);
            }
            
            activity('financial')
                ->performedOn($invoice)
                ->event('invoice.payment_recorded')
                ->withProperties([
                    'payment_id' => $payment->id,
                    'amount' => $paymentAmount,
                    'balance_due_after' => $newBalance
                ])
                ->log("Payment of " . number_format($paymentAmount / 100, 2) . " recorded");

            return $payment;
        });
    }

    public function cancel(Invoice $invoice): void
    {
        DB::transaction(function () use ($invoice) {
            $invoice = Invoice::lockForUpdate()->find($invoice->id);
            $this->postingGuard->assertCancellable($invoice);
            
            // Reverse transaction
            if ($invoice->transaction_id) {
                $this->txnService->reverseTransaction($invoice->transaction, "Reversal of Cancelled Invoice {$invoice->invoice_number}");
            }

            foreach ($invoice->payments as $payment) {
                if ($payment->transaction_id) {
                    $this->txnService->reverseTransaction($payment->transaction, "Reversal of Cancelled Payment {$payment->id}");
                }
                // We should also ideally mark the payment as cancelled if it has a status column.
            }
            
            $oldGrandTotal = $invoice->getRawOriginal('grand_total');
            $oldAmountPaid = $invoice->getRawOriginal('amount_paid');
            
            $invoice->status = InvoiceStatus::Cancelled;
            $invoice->amount_paid = 0;
            $invoice->balance_due = 0;
            $invoice->save();
            
            if ($invoice->contact_id && $invoice->transaction_id) {
                // If it was posted, it added grand_total to receivable_balance.
                // Any payments subtracted amount_paid from receivable_balance.
                // To reverse, we subtract grand_total and add back amount_paid.
                $netReversal = $oldGrandTotal - $oldAmountPaid;
                if ($netReversal > 0) {
                    $invoice->contact->decrement('receivable_balance', $netReversal);
                } elseif ($netReversal < 0) {
                    $invoice->contact->increment('receivable_balance', abs($netReversal));
                }
            }
            
            activity('financial')
                ->performedOn($invoice)
                ->event('invoice.cancelled')
                ->log("Invoice {$invoice->invoice_number} cancelled");
        });
    }

    public function generatePdf(Invoice $invoice): string
    {
        $invoice->loadMissing(['items.tax', 'items.item', 'contact']);
        $filename = "invoice_{$invoice->invoice_number}.pdf";
        
        return $this->pdfService->generateAndSave(
            'accounting::pdf.invoice',
            ['invoice' => $invoice],
            $filename
        );
    }
}
