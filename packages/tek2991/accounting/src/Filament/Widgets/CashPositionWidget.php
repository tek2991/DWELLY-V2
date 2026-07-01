<?php

namespace Tek2991\Accounting\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Facades\Accounting;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\BankAccount;
use Tek2991\Accounting\Services\AccountService;
use Carbon\Carbon;
use Tek2991\Accounting\ValueObjects\Money;

class CashPositionWidget extends BaseWidget
{
    protected static ?int $sort = 3;
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
        
        $startOfMonth = now()->startOfMonth()->toDateString();
        $endOfMonth = now()->endOfMonth()->toDateString();

        // 1. Get all Bank and Cash Account IDs
        $bankAccountIds = BankAccount::where('company_id', $companyId)
            ->pluck('account_id')
            ->toArray();
            
        $cashAccountIds = Account::where('company_id', $companyId)
            ->where('reporting_class', ReportingClass::CurrentAsset)
            ->where('name', 'like', '%Cash%')
            ->pluck('id')
            ->toArray();
            
        $allCashAccountIds = array_unique(array_merge($bankAccountIds, $cashAccountIds));

        $cashInRaw = 0;
        $cashOutRaw = 0;

        if (!empty($allCashAccountIds)) {
            $balances = $accountService->getAccountBalances($startOfMonth, $endOfMonth, $allCashAccountIds);
            foreach ($balances as $bal) {
                // Bank and Cash are Asset accounts (Debit normal)
                // Debit = Cash In, Credit = Cash Out
                $cashInRaw += $bal->total_debit ?? 0;
                $cashOutRaw += $bal->total_credit ?? 0;
            }
        }

        $cashIn = new Money($cashInRaw, $currency);
        $cashOut = new Money($cashOutRaw, $currency);
        $netCashFlow = new Money($cashInRaw - $cashOutRaw, $currency);

        return [
            Stat::make('Cash In This Month', $cashIn->format())
                ->description('Total receipts/deposits')
                ->color('success')
                ->descriptionIcon('heroicon-m-arrow-down-tray'),
            Stat::make('Cash Out This Month', $cashOut->format())
                ->description('Total payments/withdrawals')
                ->color('danger')
                ->descriptionIcon('heroicon-m-arrow-up-tray'),
            Stat::make('Net Cash Flow', $netCashFlow->format())
                ->description('Cash In - Cash Out')
                ->color($netCashFlow->getAmount() >= 0 ? 'success' : 'danger')
                ->descriptionIcon($netCashFlow->getAmount() >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down'),
        ];
    }
}
