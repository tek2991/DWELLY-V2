<?php

namespace Tek2991\Accounting\Services;

use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Payment;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Enums\DiscountType;
use Exception;

class BillService
{
    public function __construct(
        private TransactionService $txnService,
        private DocumentNumberService $docNumberService,
        private PostingGuard $postingGuard,
    ) {}

    public function create(int $companyId, array $data): Bill
    {
        return DB::transaction(function () use ($companyId, $data) {
            $bill = new Bill($data);
            $bill->company_id = $companyId;
            $bill->bill_number = $this->docNumberService->nextBillNumber($companyId);
            $bill->status = BillStatus::Draft;
            $bill->save();

            return $bill;
        });
    }

    public function recalculateTotals(Bill $bill): void
    {
        $grossSubtotal = 0;
        $totalItemDiscounts = 0;

        // First pass: Gross amounts and item discounts
        foreach ($bill->items as $item) {
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
        $bill->subtotal = $grossSubtotal / 100;
        
        // Document discount
        $docDiscount = 0;
        if ($bill->discount_type === DiscountType::Percentage) {
            $docDiscount = $preDocSubtotal * (($bill->getAttributes()['discount_rate'] ?? 0) / 100);
        } elseif ($bill->discount_type === DiscountType::Fixed) {
            $docDiscount = ($bill->getAttributes()['discount_amount'] ?? 0);
        }
        $bill->discount_amount = $docDiscount / 100;

        // Second pass: Allocation and taxes
        $remainingDocDiscount = $docDiscount;
        $itemsCount = $bill->items->count();
        $i = 0;
        
        $taxTotal = 0;
        $netItemsTotal = 0;

        foreach ($bill->items as $item) {
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
            $docMode = $bill->tax_computation_mode ?? 'exclusive';
            
            if ($item->tax_id) {
                if ($docMode === 'manual') {
                    $isInclusive = false;
                    if (is_array($item->tax_snapshot)) {
                        foreach ($item->tax_snapshot as $comp) {
                            $itemTaxAmount += (int) ($comp['amount'] ?? 0);
                        }
                    }
                } else {
                    $tax = \Tek2991\Accounting\Models\Tax::with('components')->find($item->tax_id);
                    if ($tax) {
                        $isInclusive = $docMode === 'inclusive';

                        $bill->loadMissing('contact');
                        $companyProfile = \Tek2991\Accounting\Models\CompanyProfile::firstOrCreate(
                            ['company_id' => $bill->company_id],
                            ['tax_regime' => \Tek2991\Accounting\Enums\TaxRegimeType::Generic]
                        );

                        $taxContext = new \Tek2991\Accounting\ValueObjects\TaxCalculationContext(
                            amount: $taxableValue,
                            document: $bill,
                            tax: $tax,
                            modeOverride: $docMode,
                            companyProfile: $companyProfile,
                            contact: $bill->contact
                        );

                        $taxComponents = app(\Tek2991\Accounting\Services\TaxService::class)->calculateTax($taxContext);
                        $itemTaxAmount = $taxComponents->sum('amount');
                        $item->tax_snapshot = $taxComponents->toArray();
                    }
                }
            }
            
            $itemPreTaxTotal = $isInclusive ? ($taxableValue - $itemTaxAmount) : $taxableValue;
            
            $item->line_total = $itemPreTaxTotal / 100;
            $item->tax_amount = $itemTaxAmount / 100; 
            
            $item->save();

            $netItemsTotal += $itemPreTaxTotal;
            $taxTotal += $itemTaxAmount;
        }

        $bill->tax_total = $taxTotal / 100;
        
        $grandTotal = $netItemsTotal + $taxTotal;
        $bill->grand_total = $grandTotal / 100;
        
        $balanceDue = $grandTotal - ($bill->getAttributes()['amount_paid'] ?? 0);
        $bill->balance_due = $balanceDue / 100;

        $bill->save();
    }

    public function post(Bill $bill): void
    {
        DB::transaction(function () use ($bill) {
            $bill = Bill::lockForUpdate()->find($bill->id);
            
            if ($bill->transaction_id !== null) {
                return; // Idempotent
            }
            
            $this->postingGuard->assertBillPostable($bill);

            $payableAccountId = $bill->contact->payableAccount?->id ?? \Tek2991\Accounting\Models\Account::where('company_id', $bill->company_id)
                ->where('system_role', \Tek2991\Accounting\Enums\SystemRole::TradePayable)
                ->value('id');
                
            if (!$payableAccountId) {
                throw new Exception("Contact must have a payable account to post bill.");
            }

            $entries = [];

            // CR: Payable Account
            $entries[] = [
                'account_id' => $payableAccountId,
                'type' => 'credit',
                'amount' => $bill->grand_total,
                'description' => "Bill {$bill->bill_number}",
            ];

            // DR: Expense/Asset Accounts (These are natively reduced by both line and doc discounts)
            $expenseAccounts = [];
            $taxAccounts = [];

            foreach ($bill->items as $item) {
                $expAccountId = null;
                if ($item->line_type === \Tek2991\Accounting\Enums\DocumentLineType::Item) {
                    $expAccountId = $item->item?->expense_account_id;
                } elseif ($item->line_type === \Tek2991\Accounting\Enums\DocumentLineType::Account) {
                    $expAccountId = $item->expense_account_id;
                }
                
                if (!$expAccountId) {
                    throw new Exception("Missing expense account for bill line item.");
                }

                if (!isset($expenseAccounts[$expAccountId])) {
                    $expenseAccounts[$expAccountId] = 0;
                }
                $expenseAccounts[$expAccountId] += $item->line_total;

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

            foreach ($expenseAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'type' => 'debit',
                        'amount' => $amount,
                        'description' => "Bill {$bill->bill_number} Expense",
                    ];
                }
            }

            foreach ($taxAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'type' => 'debit',
                        'amount' => $amount,
                        'description' => "Bill {$bill->bill_number} Tax",
                    ];
                }
            }

            $transaction = $this->txnService->createTransaction([
                'company_id' => $bill->company_id,
                'posted_at' => $bill->issue_date,
                'description' => "Posted Bill {$bill->bill_number}",
                'type' => \Tek2991\Accounting\Enums\TransactionType::BillPosting,
                'reference' => $bill->bill_number,
                'reviewed' => false,
                'pending' => false,
            ], $entries);

            $bill->transaction_id = $transaction->id;
            $bill->status = BillStatus::Received;
            $bill->save();
            
            if ($bill->contact_id) {
                $bill->contact->increment('payable_balance', $bill->getRawOriginal('grand_total'));
            }
            
            activity('financial')
                ->performedOn($bill)
                ->event('bill.posted')
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'grand_total' => $bill->grand_total
                ])
                ->log("Bill {$bill->bill_number} posted");
        });
    }

    public function recordPayment(Bill $bill, array $paymentData): Payment
    {
        return DB::transaction(function () use ($bill, $paymentData) {
            $bill = Bill::lockForUpdate()->find($bill->id);
            $this->postingGuard->assertPaymentAllowed($bill);
            
            $paymentAmount = $paymentData['amount'];

            $payableAccountId = $bill->contact->payableAccount?->id ?? \Tek2991\Accounting\Models\Account::where('company_id', $bill->company_id)
                ->where('system_role', \Tek2991\Accounting\Enums\SystemRole::TradePayable)
                ->value('id');

            if (!$payableAccountId) {
                throw new \Exception("Cannot record payment: No payable account found for vendor and no default Accounts Payable exists.");
            }

            // CR: Payment Bank Account
            // DR: Payable Account
            $entries = [
                [
                    'account_id' => $paymentData['payment_account_id'],
                    'type' => 'credit',
                    'amount' => $paymentAmount,
                    'description' => "Payment for Bill {$bill->bill_number}",
                ],
                [
                    'account_id' => $payableAccountId,
                    'type' => 'debit',
                    'amount' => $paymentAmount,
                    'description' => "Payment for Bill {$bill->bill_number}",
                ],
            ];

            $transaction = $this->txnService->createTransaction([
                'company_id' => $bill->company_id,
                'posted_at' => $paymentData['payment_date'],
                'description' => "Payment against Bill {$bill->bill_number}",
                'type' => \Tek2991\Accounting\Enums\TransactionType::PaymentOut,
                'reference' => "PAY-{$bill->bill_number}",
                'reviewed' => false,
                'pending' => false,
            ], $entries);

            $payment = new Payment($paymentData);
            $payment->company_id = $bill->company_id;
            $payment->transaction_id = $transaction->id;
            $bill->payments()->save($payment);

            // Update bill amounts
            $newPaid = $bill->amount_paid + $paymentAmount;
            $newBalance = $bill->grand_total - $newPaid;

            $bill->amount_paid = $newPaid;
            $bill->balance_due = $newBalance;

            if ($newBalance <= 0) {
                $bill->status = BillStatus::Paid;
            } else {
                $bill->status = BillStatus::PartiallyPaid;
            }
            
            $bill->save();
            
            if ($bill->contact_id) {
                $bill->contact->decrement('payable_balance', $paymentAmount);
            }
            
            activity('financial')
                ->performedOn($bill)
                ->event('bill.payment_made')
                ->withProperties([
                    'payment_id' => $payment->id,
                    'amount' => $paymentAmount,
                    'balance_due_after' => $newBalance
                ])
                ->log("Payment of " . number_format($paymentAmount / 100, 2) . " made");

            return $payment;
        });
    }

    public function cancel(Bill $bill): void
    {
        DB::transaction(function () use ($bill) {
            $bill = Bill::lockForUpdate()->find($bill->id);
            $this->postingGuard->assertCancellable($bill);
            
            // Reverse transaction
            if ($bill->transaction_id) {
                $this->txnService->reverseTransaction($bill->transaction, "Reversal of Cancelled Bill {$bill->bill_number}");
            }

            foreach ($bill->payments as $payment) {
                if ($payment->transaction_id) {
                    $this->txnService->reverseTransaction($payment->transaction, "Reversal of Cancelled Payment {$payment->id}");
                }
            }

            $oldGrandTotal = $bill->getRawOriginal('grand_total');
            $oldAmountPaid = $bill->getRawOriginal('amount_paid');
            
            $bill->status = BillStatus::Cancelled;
            $bill->amount_paid = 0;
            $bill->balance_due = 0;
            $bill->save();
            
            if ($bill->contact_id && $bill->transaction_id) {
                $netReversal = $oldGrandTotal - $oldAmountPaid;
                if ($netReversal > 0) {
                    $bill->contact->decrement('payable_balance', $netReversal);
                } elseif ($netReversal < 0) {
                    $bill->contact->increment('payable_balance', abs($netReversal));
                }
            }
            
            activity('financial')
                ->performedOn($bill)
                ->event('bill.cancelled')
                ->log("Bill {$bill->bill_number} cancelled");
        });
    }
}
