<?php

namespace App\Filament\Widgets;

use App\Domain\Property\Models\Property;
use Filament\Widgets\ChartWidget;

class PropertyGrowthChartWidget extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Properties Onboarded vs. Archived';

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
        $archivedData = [];

        for ($i = $monthsCount - 1; $i >= 0; $i--) {
            $monthStart = now()->subMonths($i)->startOfMonth();
            $monthEnd = now()->subMonths($i)->endOfMonth();

            $labels[] = $monthStart->format('M Y');

            $onboardedCount = Property::whereBetween('created_at', [$monthStart, $monthEnd])->count();

            $archivedCount = Property::whereIn('status', ['Archived', 'archived'])
                ->whereBetween('updated_at', [$monthStart, $monthEnd])
                ->count();

            $onboardedData[] = $onboardedCount;
            $archivedData[] = $archivedCount;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Onboarded',
                    'data' => $onboardedData,
                    'backgroundColor' => '#0284c7', // Sky blue
                    'borderColor' => '#0369a1',
                    'borderRadius' => 6,
                ],
                [
                    'label' => 'Archived',
                    'data' => $archivedData,
                    'backgroundColor' => '#94a3b8', // Slate
                    'borderColor' => '#64748b',
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
