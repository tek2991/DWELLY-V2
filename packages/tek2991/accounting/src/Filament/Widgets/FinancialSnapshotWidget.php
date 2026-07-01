<?php

namespace Tek2991\Accounting\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Facades\Accounting;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\BankAccount;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Services\AccountService;
use Carbon\Carbon;
use Tek2991\Accounting\ValueObjects\Money;

class FinancialSnapshotWidget extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $accountService = app(AccountService::class);
        $companyId = app(\Tek2991\Accounting\Contracts\CompanyAccessor::class)->getCurrentCompanyId();
        $currency = Accounting::getCurrency();
        $startDate = '1970-01-01';
        $endDate = now()->toDateString();

        // 1. Bank Balance
        $bankAccountIds = BankAccount::where('company_id', $companyId)
            ->pluck('account_id')
            ->toArray();
            
        $bankBalanceRaw = 0;
        if (!empty($bankAccountIds)) {
            $bankBalances = $accountService->getAccountBalances($startDate, $endDate, $bankAccountIds);
            foreach ($bankBalances as $bal) {
                $bankBalanceRaw += ($bal->total_debit ?? 0) - ($bal->total_credit ?? 0);
            }
        }
        $bankBalance = new Money($bankBalanceRaw, $currency);

        // 2. Cash Balance
        $cashAccountIds = Account::where('company_id', $companyId)
            ->where('reporting_class', ReportingClass::CurrentAsset)
            ->where('name', 'like', '%Cash%')
            ->whereNotIn('id', $bankAccountIds)
            ->pluck('id')
            ->toArray();
            
        $cashBalanceRaw = 0;
        if (!empty($cashAccountIds)) {
            $cashBalances = $accountService->getAccountBalances($startDate, $endDate, $cashAccountIds);
            foreach ($cashBalances as $bal) {
                $cashBalanceRaw += ($bal->total_debit ?? 0) - ($bal->total_credit ?? 0);
            }
        }
        $cashBalance = new Money($cashBalanceRaw, $currency);

        // 3. Accounts Receivable
        $arRaw = Invoice::where('company_id', $companyId)
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Cancelled])
            ->get()
            ->sum(fn ($invoice) => $invoice->getRawOriginal('balance_due'));
        $arBalance = new Money($arRaw, $currency);

        // 4. Accounts Payable
        $apRaw = Bill::where('company_id', $companyId)
            ->whereNotIn('status', [BillStatus::Draft, BillStatus::Cancelled])
            ->get()
            ->sum(fn ($bill) => $bill->getRawOriginal('balance_due'));
        $apBalance = new Money($apRaw, $currency);

        // 5. GST Payable
        $gstLiabilityIds = Account::where('company_id', $companyId)
            ->where('reporting_class', ReportingClass::GSTLiability)
            ->pluck('id')->toArray();
            
        $gstAssetIds = Account::where('company_id', $companyId)
            ->where('reporting_class', ReportingClass::GSTAsset)
            ->pluck('id')->toArray();
            
        $outputGstRaw = 0;
        if (!empty($gstLiabilityIds)) {
            $liabilityBals = $accountService->getAccountBalances($startDate, $endDate, $gstLiabilityIds);
            foreach ($liabilityBals as $bal) {
                $outputGstRaw += ($bal->total_credit ?? 0) - ($bal->total_debit ?? 0);
            }
        }
        
        $inputGstRaw = 0;
        if (!empty($gstAssetIds)) {
            $assetBals = $accountService->getAccountBalances($startDate, $endDate, $gstAssetIds);
            foreach ($assetBals as $bal) {
                $inputGstRaw += ($bal->total_debit ?? 0) - ($bal->total_credit ?? 0);
            }
        }
        $gstPayable = new Money($outputGstRaw - $inputGstRaw, $currency);

        // 6. Net Profit (YTD)
        $fiscalYearStartMonth = Accounting::getFiscalYearStart();
        $currentDate = now();
        $ytdStart = Carbon::create($currentDate->year, $fiscalYearStartMonth, 1)->startOfDay();
        if ($currentDate->month < $fiscalYearStartMonth) {
            $ytdStart->subYear();
        }
        $ytdEnd = $currentDate->copy()->endOfDay();
        
        $ytdRevenue = $accountService->getTypeTotal(AccountType::Revenue, $ytdStart->toDateString(), $ytdEnd->toDateString());
        $ytdExpense = $accountService->getTypeTotal(AccountType::Expense, $ytdStart->toDateString(), $ytdEnd->toDateString());
        $netProfit = new Money($ytdRevenue->getAmount() - $ytdExpense->getAmount(), $currency);

        return [
            Stat::make('Bank Balance', $bankBalance->format())
                ->description('Sum of all bank accounts')
                ->color('success'),
            Stat::make('Cash Balance', $cashBalance->format())
                ->description('Cash-on-hand accounts')
                ->color('success'),
            Stat::make('Accounts Receivable', $arBalance->format())
                ->description('Unpaid customer invoices')
                ->color('warning'),
            Stat::make('Accounts Payable', $apBalance->format())
                ->description('Unpaid vendor bills')
                ->color('danger'),
            Stat::make('GST Payable', $gstPayable->format())
                ->description('Output GST - Input GST')
                ->color('info'),
            Stat::make('Net Profit (YTD)', $netProfit->format())
                ->description('Current profit or loss')
                ->color($netProfit->getAmount() >= 0 ? 'success' : 'danger'),
        ];
    }
}
