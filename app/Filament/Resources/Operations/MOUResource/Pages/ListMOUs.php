<?php

namespace App\Filament\Resources\Operations\MOUResource\Pages;

use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Filament\Resources\Operations\MOUResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListMOUs extends ListRecords
{
    protected static string $resource = MOUResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All MOUs'),
            'active' => Tab::make('Active Pipeline')
                ->badge(fn () => Mou::whereNull('deleted_at')->whereNotIn('status', [
                    MouStatus::CONVERTED,
                    MouStatus::COMPLETED,
                    MouStatus::CANCELLED,
                    MouStatus::EXPIRED,
                ])->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('deleted_at')->whereNotIn('status', [
                    MouStatus::CONVERTED,
                    MouStatus::COMPLETED,
                    MouStatus::CANCELLED,
                    MouStatus::EXPIRED,
                ])),
            'converted' => Tab::make('Converted')
                ->badge(fn () => Mou::whereNull('deleted_at')->whereIn('status', [
                    MouStatus::CONVERTED,
                    MouStatus::COMPLETED,
                ])->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNull('deleted_at')->whereIn('status', [
                    MouStatus::CONVERTED,
                    MouStatus::COMPLETED,
                ])),
            'cancelled' => Tab::make('Cancelled / Archived')
                ->badge(fn () => Mou::withTrashed()->where(fn (Builder $query) => $query->whereIn('status', [
                    MouStatus::CANCELLED,
                    MouStatus::EXPIRED,
                ])->orWhereNotNull('deleted_at'))->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where(fn (Builder $q) => $q->whereIn('status', [
                    MouStatus::CANCELLED,
                    MouStatus::EXPIRED,
                ])->orWhereNotNull('deleted_at'))),
        ];
    }
}

