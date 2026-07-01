<?php

namespace Tek2991\Accounting\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Services\AccountService;

class AccountBalanceOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $accountService = app(AccountService::class);

        // For the overview, we'll just get current balances
        $startDate = '1970-01-01'; // from beginning of time
        $endDate = now()->toDateString();

        $companyId = app(\Tek2991\Accounting\Contracts\CompanyAccessor::class)->getCurrentCompanyId();
        $assets = $accountService->getTypeTotal(AccountType::Asset, $startDate, $endDate);
        $liabilities = $accountService->getTypeTotal(AccountType::Liability, $startDate, $endDate);
        $equity = $accountService->getTypeTotal(AccountType::Equity, $startDate, $endDate);

        return [
            Stat::make('Total Assets', $assets->format())
                ->description('Current total assets')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make('Total Liabilities', $liabilities->format())
                ->description('Current total liabilities')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
            Stat::make('Total Equity', $equity->format())
                ->description('Current total equity')
                ->descriptionIcon('heroicon-m-scale')
                ->color('info'),
        ];
    }
}
