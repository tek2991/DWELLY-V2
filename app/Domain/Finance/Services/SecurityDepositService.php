<?php

namespace App\Domain\Finance\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Party\Models\Party;
use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Enums\JournalEntryType;
use Tek2991\Accounting\Enums\SystemRole;
use Tek2991\Accounting\Enums\TransactionType;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Transaction;
use Tek2991\Accounting\Services\TransactionService;

class SecurityDepositService
{
    public function __construct(
        protected AccountingProvisioningService $provisioning,
        protected TransactionService $txnService
    ) {}

    /**
     * 1. Record collection of refundable security deposit from tenant.
     *
     * Journal:
     * DR: Bank Account [Asset]
     * CR: Refundable Security Deposit from Tenant [Liability]
     */
    public function recordDepositReceipt(
        TenancyAgreement $agreement,
        float $amount,
        ?Account $bankAccount = null,
        ?string $reference = null,
        ?string $paymentDate = null
    ): Transaction {
        return DB::transaction(function () use ($agreement, $amount, $bankAccount, $reference, $paymentDate) {
            $tenantParty = $agreement->tenantParty ?? $agreement->roles()->where('is_primary', true)->first()?->party;
            if (!$tenantParty) {
                throw new \InvalidArgumentException("No tenant party found for agreement {$agreement->code}");
            }

            $tenantDepositAccount = $this->provisioning->getTenantDepositAccount($tenantParty);

            if (!$bankAccount) {
                $bankAccount = Account::where('type', 'asset')
                    ->whereIn('system_role', [SystemRole::Bank, SystemRole::Cash])
                    ->first() ?? Account::where('type', 'asset')->first();
            }

            $entries = [
                [
                    'account_id' => $bankAccount->id,
                    'type' => JournalEntryType::Debit,
                    'amount' => $amount,
                    'description' => "Security Deposit received for Agreement {$agreement->code} ({$tenantParty->display_name})",
                ],
                [
                    'account_id' => $tenantDepositAccount->id,
                    'type' => JournalEntryType::Credit,
                    'amount' => $amount,
                    'description' => "Refundable Security Deposit from {$tenantParty->display_name} - {$agreement->code}",
                ],
            ];

            $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId() 
                ?? $agreement->property?->branch_id 
                ?? \App\Models\Branch::first()?->id;

            return $this->txnService->createTransaction([
                'branch_id' => $branchId,
                'type' => TransactionType::Journal,
                'description' => "Security Deposit Receipt: {$tenantParty->display_name} ({$agreement->code})",
                'posted_at' => $paymentDate ?? now()->toDateString(),
                'reference' => $reference ?? "DEP-REC-{$agreement->id}",
                'reviewed' => true,
                'pending' => false,
            ], $entries);
        });
    }

    /**
     * 2. Record placement of security deposit (split between Owner transfer & Fixed Deposit).
     *
     * Journal:
     * DR: Security Deposit with Owner [Asset]
     * DR: Fixed Deposit Account (FD Escrow) [Asset]
     * CR: Bank Account [Asset]
     */
    public function recordDepositPlacement(
        TenancyAgreement $agreement,
        float $ownerAmount,
        float $fdAmount,
        ?Account $bankAccount = null,
        ?string $reference = null,
        ?string $placementDate = null
    ): Transaction {
        return DB::transaction(function () use ($agreement, $ownerAmount, $fdAmount, $bankAccount, $reference, $placementDate) {
            $ownerParty = $agreement->property?->owner;
            if (!$ownerParty && $agreement->property_id) {
                $mouPartyId = \App\Domain\Mou\Models\Mou::where('property_id', $agreement->property_id)
                    ->whereNotNull('party_id')
                    ->latest()
                    ->value('party_id');
                if ($mouPartyId) {
                    $ownerParty = Party::find($mouPartyId);
                }
            }
            if (!$ownerParty) {
                $ownerParty = Party::whereHas('roles', fn ($q) => $q->where('name', 'owner'))
                    ->orWhereHas('ownerProfile')
                    ->first();
            }

            if (!$ownerParty) {
                throw new \InvalidArgumentException("No owner party found for agreement {$agreement->code}");
            }

            $ownerDepositAccount = $this->provisioning->getOwnerDepositAccount($ownerParty);
            
            $fdAccount = Account::where('name', 'like', '%Fixed Deposit%')->first()
                ?? Account::where('code', '1150')->first()
                ?? Account::create([
                    'code' => '1150',
                    'name' => 'Fixed Deposits (FD Escrow)',
                    'type' => \Tek2991\Accounting\Enums\AccountType::Asset,
                    'reporting_class' => \Tek2991\Accounting\Enums\ReportingClass::CurrentAsset,
                    'is_control_account' => false,
                ]);

            if (!$bankAccount) {
                $bankAccount = Account::where('type', 'asset')
                    ->whereIn('system_role', [SystemRole::Bank, SystemRole::Cash])
                    ->first() ?? Account::where('type', 'asset')->first();
            }

            $totalPlacement = $ownerAmount + $fdAmount;
            $entries = [];

            if ($ownerAmount > 0) {
                $entries[] = [
                    'account_id' => $ownerDepositAccount->id,
                    'type' => JournalEntryType::Debit,
                    'amount' => $ownerAmount,
                    'description' => "Security Deposit Transferred to Owner {$ownerParty->display_name} - {$agreement->code}",
                ];
            }

            if ($fdAmount > 0) {
                $entries[] = [
                    'account_id' => $fdAccount->id,
                    'type' => JournalEntryType::Debit,
                    'amount' => $fdAmount,
                    'description' => "Security Deposit placed into FD Escrow - {$agreement->code}",
                ];
            }

            $entries[] = [
                'account_id' => $bankAccount->id,
                'type' => JournalEntryType::Credit,
                'amount' => $totalPlacement,
                'description' => "Disbursement of Security Deposit for Placement - {$agreement->code}",
            ];

            $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId() 
                ?? $agreement->property?->branch_id 
                ?? \App\Models\Branch::first()?->id;

            return $this->txnService->createTransaction([
                'branch_id' => $branchId,
                'type' => TransactionType::Journal,
                'description' => "Security Deposit Placement: {$agreement->code} (Owner: ₹{$ownerAmount}, FD: ₹{$fdAmount})",
                'posted_at' => $placementDate ?? now()->toDateString(),
                'reference' => $reference ?? "DEP-PLACE-{$agreement->id}",
                'reviewed' => true,
                'pending' => false,
            ], $entries);
        });
    }

    /**
     * 3. Record move-out deposit settlement (with optional contractor deductions and split refunds).
     *
     * Journal:
     * - If damage deductions:
     *   DR: Tenant Deposit Liability
     *   CR: Contractor Payable (or Painter Payable)
     * - Refund remainder:
     *   DR: Tenant Deposit Liability
     *   CR: FD Escrow Account
     *   CR: Security Deposit with Owner
     * - Owner settles contractor from held deposit:
     *   DR: Contractor Payable
     *   CR: Security Deposit with Owner
     */
    public function recordDepositSettlement(
        TenancyAgreement $agreement,
        float $deductionAmount = 0.0,
        ?Party $contractorParty = null,
        float $fdLiquidation = 0.0,
        float $ownerRefund = 0.0,
        ?string $settlementDate = null
    ): array {
        return DB::transaction(function () use ($agreement, $deductionAmount, $contractorParty, $fdLiquidation, $ownerRefund, $settlementDate) {
            $tenantParty = $agreement->tenantParty ?? $agreement->roles()->where('is_primary', true)->first()?->party;
            $ownerParty = $agreement->property?->owner ?? Party::whereHas('ownerProfile')->first();
            
            $tenantDepositAccount = $this->provisioning->getTenantDepositAccount($tenantParty);
            $ownerDepositAccount = $this->provisioning->getOwnerDepositAccount($ownerParty);
            
            $fdAccount = Account::where('name', 'like', '%Fixed Deposit%')->first()
                ?? Account::where('code', '1150')->first();

            $branchId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId() 
                ?? $agreement->property?->branch_id 
                ?? \App\Models\Branch::first()?->id;

            $transactions = [];

            // Step A: Deduct contractor repair amount from Tenant Deposit
            if ($deductionAmount > 0) {
                $contractorAccount = $contractorParty 
                    ? $this->provisioning->getVendorPayableAccount($contractorParty)
                    : (Account::where('system_role', SystemRole::VendorPayable)->first() ?? Account::where('type', 'liability')->first());

                $transactions[] = $this->txnService->createTransaction([
                    'branch_id' => $branchId,
                    'type' => TransactionType::Journal,
                    'description' => "Deposit Deduction for Repair/Damages: {$tenantParty->display_name} ({$agreement->code})",
                    'posted_at' => $settlementDate ?? now()->toDateString(),
                    'reference' => "DEP-DEDUCT-{$agreement->id}",
                    'reviewed' => true,
                    'pending' => false,
                ], [
                    [
                        'account_id' => $tenantDepositAccount->id,
                        'type' => JournalEntryType::Debit,
                        'amount' => $deductionAmount,
                        'description' => "Deduction from Deposit for damages - {$agreement->code}",
                    ],
                    [
                        'account_id' => $contractorAccount->id,
                        'type' => JournalEntryType::Credit,
                        'amount' => $deductionAmount,
                        'description' => "Payable for repairs deducted from {$tenantParty->display_name}'s deposit",
                    ],
                ]);

                // Step B: Settle Contractor from Owner's held deposit
                $transactions[] = $this->txnService->createTransaction([
                    'branch_id' => $branchId,
                    'type' => TransactionType::Journal,
                    'description' => "Contractor Settlement from Owner Held Deposit: {$agreement->code}",
                    'posted_at' => $settlementDate ?? now()->toDateString(),
                    'reference' => "DEP-SETTLE-{$agreement->id}",
                    'reviewed' => true,
                    'pending' => false,
                ], [
                    [
                        'account_id' => $contractorAccount->id,
                        'type' => JournalEntryType::Debit,
                        'amount' => $deductionAmount,
                        'description' => "Contractor Repair Settlement against Owner Held Deposit",
                    ],
                    [
                        'account_id' => $ownerDepositAccount->id,
                        'type' => JournalEntryType::Credit,
                        'amount' => $deductionAmount,
                        'description' => "Reduction of Owner Held Deposit for repair payment",
                    ],
                ]);
            }

            // Step C: Refund Remaining Deposit to Tenant
            $remainingRefund = $fdLiquidation + $ownerRefund;
            if ($remainingRefund > 0) {
                $refundEntries = [
                    [
                        'account_id' => $tenantDepositAccount->id,
                        'type' => JournalEntryType::Debit,
                        'amount' => $remainingRefund,
                        'description' => "Refund of Remaining Security Deposit to {$tenantParty->display_name}",
                    ]
                ];

                if ($fdLiquidation > 0 && $fdAccount) {
                    $refundEntries[] = [
                        'account_id' => $fdAccount->id,
                        'type' => JournalEntryType::Credit,
                        'amount' => $fdLiquidation,
                        'description' => "FD Liquidation for Deposit Refund - {$agreement->code}",
                    ];
                }

                if ($ownerRefund > 0) {
                    $refundEntries[] = [
                        'account_id' => $ownerDepositAccount->id,
                        'type' => JournalEntryType::Credit,
                        'amount' => $ownerRefund,
                        'description' => "Owner Deposit Refund Portion - {$agreement->code}",
                    ];
                }

                $transactions[] = $this->txnService->createTransaction([
                    'branch_id' => $branchId,
                    'type' => TransactionType::Journal,
                    'description' => "Deposit Refund to Tenant: {$tenantParty->display_name} ({$agreement->code})",
                    'posted_at' => $settlementDate ?? now()->toDateString(),
                    'reference' => "DEP-REFUND-{$agreement->id}",
                    'reviewed' => true,
                    'pending' => false,
                ], $refundEntries);
            }

            return $transactions;
        });
    }
}
