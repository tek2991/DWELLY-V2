<?php

namespace App\Filament\Widgets;

use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpportunityStatsOverview extends BaseWidget
{
    protected static ?int $sort = 2;

    protected function getStats(): array
    {
        $total = Opportunity::count();
        
        $closed = Opportunity::whereIn('status', [
            OpportunityStatus::CLOSED_LOST,
            OpportunityStatus::CANCELLED,
        ])->count();

        $convertedToMou = Opportunity::whereIn('status', [
            OpportunityStatus::READY_FOR_MOU,
            OpportunityStatus::MOU_CREATED,
            OpportunityStatus::MOU_SIGNED,
        ])->count();

        $convertedToProperty = Opportunity::where('status', OpportunityStatus::CONVERTED)->count();

        return [
            Stat::make('Total Opportunities', $total)
                ->description('Total leads & deals')
                ->descriptionIcon('heroicon-m-funnel')
                ->color('primary'),

            Stat::make('Closed Deals', $closed)
                ->description('Lost or cancelled')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('gray'),

            Stat::make('Converted to MOU', $convertedToMou)
                ->description('MOU drafted / signed')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('warning'),

            Stat::make('Converted to Property', $convertedToProperty)
                ->description('Successfully onboarded')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
