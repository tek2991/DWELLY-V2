<?php

namespace App\Filament\Widgets;

use App\Domain\Property\Models\Property;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PropertyStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $total = Property::count();
        $vacant = Property::where('status', 'Vacant')->count();
        $onboarding = Property::where('status', 'Onboarding')->count();
        $archived = Property::where('status', 'Archived')
            ->orWhere('is_listed', false)
            ->count();

        return [
            Stat::make('Total Properties', $total)
                ->description('Total portfolio')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make('Vacant Properties', $vacant)
                ->description('Ready for lease')
                ->descriptionIcon('heroicon-m-home')
                ->color('success'),

            Stat::make('Onboarding Properties', $onboarding)
                ->description('In setup pipeline')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('warning'),

            Stat::make('Archived Properties', $archived)
                ->description('Inactive / unlisted')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('gray'),
        ];
    }
}
