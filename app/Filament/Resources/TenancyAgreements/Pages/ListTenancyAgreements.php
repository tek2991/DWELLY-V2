<?php

namespace App\Filament\Resources\TenancyAgreements\Pages;

use App\Filament\Resources\TenancyAgreements\TenancyAgreementResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTenancyAgreements extends ListRecords
{
    protected static string $resource = TenancyAgreementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All'),
            'active' => Tab::make('Active')
                ->badge(fn () => $this->getModel()::where('status', 'active')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', 'active')),
            'onboarding' => Tab::make('Drafts & Onboarding')
                ->badge(fn () => $this->getModel()::whereIn('status', ['draft', 'signed'])->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['draft', 'signed'])),
            'deboarding' => Tab::make('Deboarding / Vacated')
                ->badge(fn () => $this->getModel()::whereIn('status', ['deboarding_initiated', 'vacated', 'terminated'])->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', ['deboarding_initiated', 'vacated', 'terminated'])),
        ];
    }
}
