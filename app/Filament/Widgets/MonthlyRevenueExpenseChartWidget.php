<?php

namespace App\Filament\Widgets;

use App\Domain\Finance\Models\OwnerPayout;
use Carbon\Carbon;
use Filament\Widgets\ChartWidget;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Services\AccountService;

class MonthlyRevenueExpenseChartWidget extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Revenue vs. Expenses';

    protected ?string $maxHeight = '260px';

    public ?string $filter = '6';

    protected function getFilters(): ?array
    {
        return [
            '6' => 'Last 6 Months',
            '12' => 'Last 12 Months',
        ];
    }

    protected function getData(): array
    {
        $monthsCount = (int) ($this->filter ?? 6);
        if ($monthsCount !== 12) {
            $monthsCount = 6;
        }

        $accountService = app(AccountService::class);

        $labels = [];
        $revenueData = [];
        $expenseData = [];

        for ($i = $monthsCount - 1; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();

            $labels[] = $monthStart->format('M Y');

            // 1. Double-entry ledger totals
            $ledgerRev = (float) $accountService->getTypeTotal(AccountType::Revenue, $monthStart->toDateString(), $monthEnd->toDateString())->getAmount();
            $ledgerExp = (float) $accountService->getTypeTotal(AccountType::Expense, $monthStart->toDateString(), $monthEnd->toDateString())->getAmount();

            // 2. Direct operational fallbacks if ledger has not posted full accruals
            $invoicedRev = (float) (Invoice::whereBetween('issue_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid])
                ->sum('grand_total') / 100);

            $payoutsExp = (float) OwnerPayout::whereBetween('processed_at', [$monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay()])
                ->where('status', 'completed')
                ->sum('amount');

            $billsExp = (float) (Bill::whereBetween('issue_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                ->sum('grand_total') / 100);

            $totalRevenue = max($ledgerRev, $invoicedRev);
            $totalExpenses = max($ledgerExp, $payoutsExp + $billsExp);

            $revenueData[] = round($totalRevenue, 2);
            $expenseData[] = round($totalExpenses, 2);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₹)',
                    'data' => $revenueData,
                    'backgroundColor' => '#10b981', // Emerald
                    'borderColor' => '#059669',
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Expenses (₹)',
                    'data' => $expenseData,
                    'backgroundColor' => '#f43f5e', // Rose
                    'borderColor' => '#e11d48',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
