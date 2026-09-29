<?php

namespace App\Filament\Resources\Operations\OpportunityResource\Pages;

use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Filament\Resources\Operations\OpportunityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListOpportunities extends ListRecords
{
    protected static string $resource = OpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Leads'),
            'active' => Tab::make('Active Pipeline')
                ->badge(fn () => Opportunity::whereNotIn('status', [
                    OpportunityStatus::CONVERTED,
                    OpportunityStatus::CLOSED_LOST,
                ])->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', [
                    OpportunityStatus::CONVERTED,
                    OpportunityStatus::CLOSED_LOST,
                ])),
            'converted' => Tab::make('Converted')
                ->badge(fn () => Opportunity::where('status', OpportunityStatus::CONVERTED)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OpportunityStatus::CONVERTED)),
            'closed_lost' => Tab::make('Closed Lost')
                ->badge(fn () => Opportunity::where('status', OpportunityStatus::CLOSED_LOST)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', OpportunityStatus::CLOSED_LOST)),
        ];
    }
}
