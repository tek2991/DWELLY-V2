<?php

namespace App\Filament\Widgets;

use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OpportunityStatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        return [
            Stat::make('My Opportunities', Opportunity::where('assigned_user_id', auth()->id())->count())
                ->description('Assigned to you')
                ->descriptionIcon('heroicon-m-user')
                ->color('primary'),
                
            Stat::make('New Opportunities', Opportunity::where('status', OpportunityStatus::NEW)->count())
                ->description('Needs contact')
                ->descriptionIcon('heroicon-m-bell-alert')
                ->color('danger'),
                
            Stat::make('Awaiting Site Visit', Opportunity::whereIn('status', [OpportunityStatus::CONTACTED, OpportunityStatus::SITE_VISIT_SCHEDULED])->count())
                ->description('Needs action')
                ->descriptionIcon('heroicon-m-calendar')
                ->color('warning'),
                
            Stat::make('Negotiation', Opportunity::where('status', OpportunityStatus::NEGOTIATION)->count())
                ->description('In active talks')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('purple'),
                
            Stat::make('Ready for MOU', Opportunity::where('status', OpportunityStatus::READY_FOR_MOU)->count())
                ->description('Pending legal')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('success'),
        ];
    }
}
