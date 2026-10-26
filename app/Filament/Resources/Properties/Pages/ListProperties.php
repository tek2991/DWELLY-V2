<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Domain\Property\Enums\PropertyStatus;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Properties\PropertyResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProperties extends ListRecords
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Properties'),
            'vacant' => Tab::make('Vacant')
                ->badge(fn () => Property::where('status', PropertyStatus::VACANT->value)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PropertyStatus::VACANT->value)),
            'occupied' => Tab::make('Occupied')
                ->badge(fn () => Property::where('status', PropertyStatus::OCCUPIED->value)->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PropertyStatus::OCCUPIED->value)),
            'onboarding' => Tab::make('Onboarding')
                ->badge(fn () => Property::where('status', PropertyStatus::ONBOARDING->value)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PropertyStatus::ONBOARDING->value)),
            'maintenance' => Tab::make('Maintenance')
                ->badge(fn () => Property::withOpenMaintenance()->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->withOpenMaintenance()),
            'draft' => Tab::make('Draft')
                ->badge(fn () => Property::where('status', PropertyStatus::DRAFT->value)->count())
                ->badgeColor('gray')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', PropertyStatus::DRAFT->value)),
        ];
    }
}
