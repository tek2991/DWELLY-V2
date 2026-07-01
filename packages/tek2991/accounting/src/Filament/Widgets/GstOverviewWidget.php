<?php

namespace Tek2991\Accounting\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Tek2991\Accounting\Enums\ReportingClass;
use Tek2991\Accounting\Facades\Accounting;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Services\AccountService;
use Tek2991\Accounting\ValueObjects\Money;

class GstOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 7;
    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4; // 3 for GST stats, 1 for next return due
    }

    protected function getStats(): array
    {
        $accountService = app(AccountService::class);
        $companyId = app(\Tek2991\Accounting\Services\BranchContext::class)->getCurrentId();
        $currency = Accounting::getCurrency();
        $startDate = '1970-01-01';
        $endDate = now()->toDateString();

        $gstLiabilityIds = Account::where('reporting_class', ReportingClass::GSTLiability)
            ->pluck('id')->toArray();
            
        $gstAssetIds = Account::where('reporting_class', ReportingClass::GSTAsset)
            ->pluck('id')->toArray();
            
        $outputGstRaw = 0;
        if (!empty($gstLiabilityIds)) {
            $liabilityBals = $accountService->getAccountBalances($startDate, $endDate, $gstLiabilityIds);
            foreach ($liabilityBals as $bal) {
                // Liability is credit normal
                $outputGstRaw += ($bal->total_credit ?? 0) - ($bal->total_debit ?? 0);
            }
        }
        
        $inputGstRaw = 0;
        if (!empty($gstAssetIds)) {
            $assetBals = $accountService->getAccountBalances($startDate, $endDate, $gstAssetIds);
            foreach ($assetBals as $bal) {
                // Asset is debit normal
                $inputGstRaw += ($bal->total_debit ?? 0) - ($bal->total_credit ?? 0);
            }
        }

        $outputGst = new Money($outputGstRaw, $currency);
        $inputGst = new Money($inputGstRaw, $currency);
        $gstPayable = new Money($outputGstRaw - $inputGstRaw, $currency);

        return [
            Stat::make('Output GST', $outputGst->format())
                ->description('Collected on sales')
                ->color('danger'),
            Stat::make('Input GST', $inputGst->format())
                ->description('Paid on purchases')
                ->color('success'),
            Stat::make('GST Payable', $gstPayable->format())
                ->description('Net amount due')
                ->color($gstPayable->getAmount() > 0 ? 'warning' : 'success'),
            Stat::make('Next Return Due', '15 ' . now()->addMonth()->format('M Y'))
                ->description('GSTR-3B (Estimated)')
                ->color('primary')
                ->descriptionIcon('heroicon-m-calendar'),
        ];
    }
}
