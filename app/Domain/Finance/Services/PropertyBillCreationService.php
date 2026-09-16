<?php

namespace App\Domain\Finance\Services;

use App\Domain\Property\Models\Property;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Enums\DocumentLineType;
use Tek2991\Accounting\Enums\SystemRole;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\BillItem;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Services\BillService;
use Tek2991\Accounting\Services\BranchContext;
use Tek2991\Accounting\Services\DocumentNumberService;

class PropertyBillCreationService
{
    public function __construct(
        protected BillService $billService,
        protected DocumentNumberService $docNumberService,
        protected BranchContext $branchContext,
    ) {}

    /**
     * Create an accounting-compliant bill with line items and double-entry GL readiness.
     */
    public function createBill(array $data, ?User $creator = null): Bill
    {
        return DB::transaction(function () use ($data, $creator) {
            $property = ! empty($data['property_id'])
                ? Property::find($data['property_id'])
                : null;

            $branchId = $this->branchContext->getCurrentId()
                ?? $property?->branch_id
                ?? Branch::first()?->id;

            $branch = $branchId ? Branch::find($branchId) : null;
            $billNumber = $this->docNumberService->nextBillNumber($branch);

            $category = $data['category'] ?? 'general_vendor';
            $userRemarks = trim($data['notes'] ?? '');
            $notes = $this->composeBillNotes($category, $userRemarks, $property);

            $bill = Bill::create([
                'branch_id' => $branchId,
                'contact_id' => $data['contact_id'],
                'bill_number' => $billNumber,
                'vendor_reference' => $data['vendor_reference'] ?? null,
                'seller_invoice_path' => $data['seller_invoice_path'] ?? null,
                'reference_type' => $property ? Property::class : null,
                'reference_id' => $property?->id,
                'status' => BillStatus::Draft,
                'issue_date' => $data['issue_date'] ?? now()->toDateString(),
                'due_date' => $data['due_date'] ?? now()->addDays(14)->toDateString(),
                'currency_code' => config('accounting.default_currency', 'INR'),
                'exchange_rate' => 1.0,
                'notes' => $notes,
            ]);

            $amount = (float) $data['amount'];
            $expenseAccountId = ! empty($data['expense_account_id'])
                ? (int) $data['expense_account_id']
                : $this->resolveDefaultExpenseAccountId($category);

            // Mandatory line item for accounting double-entry balance
            BillItem::create([
                'bill_id' => $bill->id,
                'line_type' => DocumentLineType::Account,
                'sort_order' => 1,
                'description' => $notes,
                'quantity' => 1,
                'unit_price' => $amount,
                'line_total' => $amount,
                'gross_amount' => $amount,
                'net_amount' => $amount,
                'expense_account_id' => $expenseAccountId,
            ]);

            // Synchronize totals in minor and major units
            $this->billService->recalculateTotals($bill);
            $bill->refresh();

            // Ensure Trade Payable control account is ready for GL posting
            $this->ensureTradePayableAccountExists();

            // Auto-post if requested and user is authorized
            $canAutoPost = ! empty($data['auto_post'])
                && (! $creator || $creator->can('billing.bill.approve') || $creator->hasAnyRole(['Business Owner', 'Accountant']) || $creator->roles->isEmpty());

            if ($canAutoPost) {
                $this->billService->post($bill);
                $bill->refresh();
            }

            return $bill;
        });
    }

    /**
     * Map category to canonical notes with keyword triggers for BillingPropertyResolver.
     */
    public function composeBillNotes(string $category, string $customRemarks, ?Property $property = null): string
    {
        $prefix = match ($category) {
            'utility_electricity' => '[Property Utility - Electricity / Power]',
            'utility_water' => '[Property Utility - Water Supply]',
            'utility_gas_internet' => '[Property Utility - Gas & Internet]',
            'society_maintenance' => '[Society Maintenance / HOA Dues]',
            'turnover_cleaning' => '[Turnover Service - Deep Cleaning]',
            'turnover_painting' => '[Turnover Service - Painting & Touchups]',
            'turnover_pest' => '[Turnover Service - Pest Control]',
            default => '[Operating Vendor Expense]',
        };

        $parts = [$prefix];

        if ($property) {
            $propIdentifier = $property->building_name . ($property->address_line_1 ? " ({$property->address_line_1})" : '');
            $parts[] = "Property: {$propIdentifier}";
        }

        if (! empty($customRemarks)) {
            $parts[] = $customRemarks;
        }

        return implode(' • ', $parts);
    }

    /**
     * Resolve the appropriate GL expense account for the chosen category.
     */
    public function resolveDefaultExpenseAccountId(string $category): int
    {
        $account = match ($category) {
            'utility_electricity' => Account::where('code', '6220')->first()
                ?? Account::where('type', AccountType::Expense)->where('name', 'like', '%Electric%')->first(),

            'utility_water', 'utility_gas_internet' => Account::where('code', '6230')->first()
                ?? Account::where('type', AccountType::Expense)->where(function ($q) {
                    $q->where('name', 'like', '%Internet%')
                        ->orWhere('name', 'like', '%Utility%')
                        ->orWhere('name', 'like', '%Water%');
                })->first(),

            'society_maintenance' => Account::where('type', AccountType::Expense)->where(function ($q) {
                $q->where('name', 'like', '%Society%')
                    ->orWhere('name', 'like', '%HOA%')
                    ->orWhere('name', 'like', '%Maintenance%');
            })->first() ?? Account::where('code', '5110')->first(),

            'turnover_cleaning', 'turnover_painting', 'turnover_pest' => Account::where('code', '5110')->first()
                ?? Account::where('type', AccountType::Expense)->where(function ($q) {
                    $q->where('name', 'like', '%Contractor%')
                        ->orWhere('name', 'like', '%Maintenance%')
                        ->orWhere('name', 'like', '%Cleaning%');
                })->first(),

            default => null,
        };

        if ($account) {
            return $account->id;
        }

        // Generic fallback to any Expense account
        $fallback = Account::where('type', AccountType::Expense)->first();
        if ($fallback) {
            return $fallback->id;
        }

        // Create fallback if system has zero expense accounts
        $created = Account::create([
            'code' => '6200',
            'name' => 'General Operating Expense',
            'type' => AccountType::Expense,
            'reporting_class' => \Tek2991\Accounting\Enums\ReportingClass::OperatingExpense,
            'is_control_account' => false,
            'currency_code' => config('accounting.default_currency', 'INR'),
        ]);

        return $created->id;
    }

    /**
     * Ensure Trade Payable control account exists for double-entry GL balance.
     */
    protected function ensureTradePayableAccountExists(): void
    {
        $exists = Account::where('system_role', SystemRole::TradePayable)->exists();
        if (! $exists) {
            Account::firstOrCreate(
                ['code' => '2110'],
                [
                    'name' => 'Accounts Payable (Sundry Creditors)',
                    'type' => AccountType::Liability,
                    'reporting_class' => \Tek2991\Accounting\Enums\ReportingClass::CurrentLiability,
                    'system_role' => SystemRole::TradePayable,
                    'is_control_account' => true,
                    'currency_code' => config('accounting.default_currency', 'INR'),
                ]
            );
        }
    }
}
