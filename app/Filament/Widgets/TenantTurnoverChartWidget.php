<?php

namespace App\Filament\Widgets;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use Filament\Widgets\ChartWidget;

class TenantTurnoverChartWidget extends ChartWidget
{
    protected static ?int $sort = 5;

    protected ?string $heading = 'Tenants Onboarded vs. Deboarded';

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

        $labels = [];
        $onboardedData = [];
        $deboardedData = [];

        for ($i = $monthsCount - 1; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();

            $labels[] = $monthStart->format('M Y');

            // Tenants Onboarded (Move-ins with start_date in month or created in month)
            $onboardedCount = TenancyAgreement::where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('start_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->orWhere(function ($sub) use ($monthStart, $monthEnd) {
                        $sub->whereNull('start_date')
                            ->whereBetween('created_at', [$monthStart, $monthEnd]);
                    });
            })->count();

            // Tenants Deboarded (Move-outs completed or vacated in month)
            $deboardedCount = TenantDeboarding::where(function ($q) use ($monthStart, $monthEnd) {
                $q->whereBetween('actual_vacating_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->orWhereBetween('completed_at', [$monthStart, $monthEnd])
                    ->orWhere(function ($sub) use ($monthStart, $monthEnd) {
                        $sub->where('status', DeboardingStatus::COMPLETED)
                            ->whereBetween('updated_at', [$monthStart, $monthEnd]);
                    });
            })->count();

            $onboardedData[] = $onboardedCount;
            $deboardedData[] = $deboardedCount;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Move-Ins (Onboarded)',
                    'data' => $onboardedData,
                    'backgroundColor' => '#10b981', // Emerald
                    'borderColor' => '#059669',
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Move-Outs (Deboarded)',
                    'data' => $deboardedData,
                    'backgroundColor' => '#f59e0b', // Amber
                    'borderColor' => '#d97706',
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
