<?php

namespace Tek2991\Accounting\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Services\AccountService;

class RevenueExpenseChartWidget extends ChartWidget
{
    protected static ?int $sort = 6;
    protected ?string $heading = 'Revenue & Expense Trend';
    protected ?string $maxHeight = '250px';
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $accountService = app(AccountService::class);
        
        $labels = [];
        $revenueData = [];
        $expenseData = [];

        // Loop through the last 6 months
        for ($i = 5; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();
            
            $labels[] = $monthStart->format('M Y');
            
            $revenue = $accountService->getTypeTotal(AccountType::Revenue, $monthStart->toDateString(), $monthEnd->toDateString());
            $expense = $accountService->getTypeTotal(AccountType::Expense, $monthStart->toDateString(), $monthEnd->toDateString());
            
            $revenueData[] = $revenue->getAmount();
            $expenseData[] = $expense->getAmount();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Revenue',
                    'data' => $revenueData,
                    'backgroundColor' => '#10b981', // success
                    'borderColor' => '#10b981',
                ],
                [
                    'label' => 'Expenses',
                    'data' => $expenseData,
                    'backgroundColor' => '#ef4444', // danger
                    'borderColor' => '#ef4444',
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
