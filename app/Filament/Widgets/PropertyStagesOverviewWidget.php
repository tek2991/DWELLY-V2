<?php

namespace App\Filament\Widgets;

use App\Domain\Property\Models\Property;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PropertyStagesOverviewWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $total = Property::count();
        $occupied = Property::whereIn('status', ['Occupied', 'occupied'])->count();
        $vacant = Property::whereIn('status', ['Vacant', 'vacant'])->count();
        $onboarding = Property::whereIn('status', ['Onboarding', 'onboarding'])->count();
        $maintenance = Property::whereIn('status', ['Maintenance', 'maintenance', 'under_maintenance'])->count();
        $archived = Property::whereIn('status', ['Archived', 'archived'])->count();

        $activeLeaseable = $occupied + $vacant + $maintenance;
        $occupancyRate = $activeLeaseable > 0 ? round(($occupied / $activeLeaseable) * 100, 1) : 0.0;

        return [
            Stat::make('Total Portfolio', $total)
                ->description("{$occupancyRate}% active occupancy")
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),

            Stat::make('Occupied Units', $occupied)
                ->description('Active tenant leases')
                ->descriptionIcon('heroicon-m-user-group')
                ->color('success'),

            Stat::make('Vacant (Ready)', $vacant)
                ->description('Available for immediate lease')
                ->descriptionIcon('heroicon-m-home')
                ->color('info'),

            Stat::make('In Onboarding', $onboarding)
                ->description('Setup & activation pipeline')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('warning'),

            Stat::make('Under Maintenance', $maintenance)
                ->description('Turnover / repairs in progress')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('danger'),

            Stat::make('Archived / Inactive', $archived)
                ->description('Offboarded / unlisted units')
                ->descriptionIcon('heroicon-m-archive-box')
                ->color('gray'),
        ];
    }
}
