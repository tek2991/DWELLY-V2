<?php

namespace Tek2991\Accounting\Services;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Models\DebitNote;
use Tek2991\Accounting\Enums\DebitNoteStatus;
use Exception;

class DebitNoteService
{
    public function __construct(
        private TransactionService $txnService,
        private DocumentNumberService $docNumberService,
        private PostingGuard $postingGuard,
    ) {}

    public function create(?Branch $branch, array $data): DebitNote
    {
        return DB::transaction(function () use ($branch, $data) {
            $dn = new DebitNote($data);
            $dn->branch_id = $branch?->id;
            $dn->debit_note_number = $this->docNumberService->nextDebitNoteNumber($branch);
            $dn->status = DebitNoteStatus::Draft;
            $dn->save();

            return $dn;
        });
    }

    public function createFromBill(\Tek2991\Accounting\Models\Bill $bill, array $quantitiesToReturn): DebitNote
    {
        return DB::transaction(function () use ($bill, $quantitiesToReturn) {
            $dn = new DebitNote([
                'contact_id' => $bill->contact_id,
                'bill_id' => $bill->id,
                'issue_date' => now(),
            ]);
            $dn->branch_id = $bill->branch_id;
            $dn->debit_note_number = $this->docNumberService->nextDebitNoteNumber($bill->branch);
            $dn->status = DebitNoteStatus::Draft;
            $dn->save();

            foreach ($bill->items as $origItem) {
                $returnQty = $quantitiesToReturn[$origItem->id] ?? 0;
                if ($returnQty > 0) {
                    $dn->items()->create([
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

            $this->recalculateTotals($dn);

            return $dn;
        });
    }

    public function recalculateTotals(DebitNote $dn): void
    {
        $subtotal = 0;
        $taxTotal = 0;

        $isLinkedToBill = $dn->bill_id !== null;
        if ($isLinkedToBill) {
            $dn->loadMissing('bill.items');
            $billItems = $dn->bill->items->keyBy('item_id');
        }

        foreach ($dn->items as $item) {
            $qty = ($item->getAttributes()['quantity'] ?? 0);
            
            if ($isLinkedToBill && isset($billItems[$item->item_id])) {
                $origItem = $billItems[$item->item_id];
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
                        
                        $dn->loadMissing('contact');
                        $branch = \App\Models\Branch::find($dn->branch_id);

                        $taxContext = new \Tek2991\Accounting\ValueObjects\TaxCalculationContext(
                            amount: $baseLineTotal,
                            document: $dn,
                            tax: $tax,
                            modeOverride: $isInclusive ? 'inclusive' : 'exclusive',
                            branch: $branch,
                            contact: $dn->contact
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

        $dn->subtotal = $subtotal / 100;
        $dn->tax_total = $taxTotal / 100;
        
        $grandTotal = $subtotal + $taxTotal;
        $dn->grand_total = $grandTotal / 100;
        
        $balanceRemaining = $grandTotal - ($dn->getAttributes()['applied_amount'] ?? 0);
        $dn->balance_remaining = $balanceRemaining / 100;

        $dn->save();
    }

    public function post(DebitNote $dn): void
    {
        DB::transaction(function () use ($dn) {
            $dn = DebitNote::lockForUpdate()->find($dn->id);
            
            if ($dn->transaction_id !== null) {
                return;
            }
            
            if ($dn->status !== DebitNoteStatus::Draft) {
                throw new Exception("Only draft debit notes can be posted.");
            }

            $payableAccountId = $dn->contact->payableAccount?->id ?? \Tek2991\Accounting\Models\Account::where('system_role', \Tek2991\Accounting\Enums\SystemRole::TradePayable)
                ->value('id');

            if (!$payableAccountId) {
                throw new \Exception("Cannot post debit note: No payable account found for vendor and no default Accounts Payable exists.");
            }

            $entries = [];

            // DR: Payable Account
            $entries[] = [
                'account_id' => $payableAccountId,
                'type' => 'debit',
                'amount' => $dn->grand_total,
                'description' => "Debit Note {$dn->debit_note_number}",
            ];

            // CR: Expense Accounts & Taxes (reversal)
            $expenseAccounts = [];
            $taxAccounts = [];

            foreach ($dn->items as $item) {
                $expAccountId = $item->item->expense_account_id ?? null;
                if (!$expAccountId) {
                    throw new Exception("Missing expense account for item.");
                }

                if (!isset($expenseAccounts[$expAccountId])) {
                    $expenseAccounts[$expAccountId] = 0;
                }
                $expenseAccounts[$expAccountId] += $item->line_total;

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
                        'type' => 'credit',
                        'amount' => $amount,
                        'description' => "Debit Note {$dn->debit_note_number} Expense Reversal",
                    ];
                }
            }

            foreach ($taxAccounts as $accId => $amount) {
                if ($amount > 0) {
                    $entries[] = [
                        'account_id' => $accId,
                        'type' => 'credit',
                        'amount' => $amount,
                        'description' => "Debit Note {$dn->debit_note_number} Tax Reversal",
                    ];
                }
            }

            $transaction = $this->txnService->createTransaction([
                'branch_id' => $dn->branch_id,
                'type' => \Tek2991\Accounting\Enums\TransactionType::DebitNote,
                'description' => "Posted Debit Note {$dn->debit_note_number}",
                'posted_at' => $dn->issue_date?->toDateString() ?? now()->toDateString(),
                'reference' => $dn->debit_note_number,
            ], $entries);

            $dn->transaction_id = $transaction->id;
            $dn->status = DebitNoteStatus::Issued;
            $dn->save();
            
            activity('financial')
                ->performedOn($dn)
                ->event('debit_note.posted')
                ->withProperties([
                    'transaction_id' => $transaction->id,
                    'grand_total' => $dn->grand_total
                ])
                ->log("Debit Note {$dn->debit_note_number} posted");
        });
    }

    public function cancel(DebitNote $dn): void
    {
        DB::transaction(function () use ($dn) {
            $dn = DebitNote::lockForUpdate()->find($dn->id);
            
            if ($dn->status === DebitNoteStatus::Cancelled) {
                return;
            }
            if ($dn->status === DebitNoteStatus::Applied) {
                throw new Exception("Cannot cancel an applied debit note.");
            }
            
            if ($dn->transaction_id) {
                $this->txnService->reverseTransaction($dn->transaction, "Reversal of Cancelled DN: {$dn->debit_note_number}");
            }

            $dn->status = DebitNoteStatus::Cancelled;
            $dn->save();
            
            activity('financial')
                ->performedOn($dn)
                ->event('debit_note.cancelled')
                ->log("Debit Note {$dn->debit_note_number} cancelled");
        });
    }

    public function applyToDocument(DebitNote $dn, \Tek2991\Accounting\Models\Bill $bill, int $amount): void
    {
        DB::transaction(function () use ($dn, $bill, $amount) {
            $dn = DebitNote::lockForUpdate()->find($dn->id);
            $bill = \Tek2991\Accounting\Models\Bill::lockForUpdate()->find($bill->id);
            
            if ($dn->status !== \Tek2991\Accounting\Enums\DebitNoteStatus::Issued && $dn->status !== \Tek2991\Accounting\Enums\DebitNoteStatus::PartiallyApplied) {
                throw new Exception("Only issued or partially applied debit notes can be applied.");
            }
            if ($dn->contact_id !== $bill->contact_id) {
                throw new Exception("Debit note and bill must belong to the same contact.");
            }
            if ($amount > ($dn->getRawOriginal('balance_remaining'))) {
                throw new Exception("Cannot apply more than the remaining balance of the debit note.");
            }
            if ($amount > ($bill->getRawOriginal('balance_due'))) {
                throw new Exception("Cannot apply more than the remaining balance of the bill.");
            }

            // Update DN
            $newApplied = $dn->getRawOriginal('applied_amount') + $amount;
            $dn->applied_amount = $newApplied;
            $dn->balance_remaining = $dn->getRawOriginal('grand_total') - $newApplied;
            if (($dn->getRawOriginal('grand_total') - $newApplied) <= 0) {
                $dn->status = \Tek2991\Accounting\Enums\DebitNoteStatus::Applied;
            } else {
                $dn->status = \Tek2991\Accounting\Enums\DebitNoteStatus::PartiallyApplied;
            }
            $dn->save();

            // Update Bill
            $newPaid = $bill->getRawOriginal('amount_paid') + $amount;
            $bill->amount_paid = $newPaid;
            $bill->balance_due = $bill->getRawOriginal('grand_total') - $newPaid;
            if (($bill->getRawOriginal('grand_total') - $newPaid) <= 0) {
                $bill->status = \Tek2991\Accounting\Enums\BillStatus::Paid;
            } else {
                $bill->status = \Tek2991\Accounting\Enums\BillStatus::PartiallyPaid;
            }
            $bill->save();

            // Record application
            activity('financial')
                ->performedOn($dn)
                ->event('debit_note.applied')
                ->withProperties([
                    'bill_id' => $bill->id,
                    'amount' => $amount
                ])
                ->log("Applied " . number_format($amount / 100, 2) . " to Bill {$bill->bill_number}");
                
            activity('financial')
                ->performedOn($bill)
                ->event('bill.debit_applied')
                ->withProperties([
                    'debit_note_id' => $dn->id,
                    'amount' => $amount
                ])
                ->log("Debit Note {$dn->debit_note_number} applied for " . number_format($amount / 100, 2));
        });
    }
}
