<?php

namespace App\Filament\Resources\Operations\TenantDeboardingResource\Pages;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTenantDeboardings extends ListRecords
{
    protected static string $resource = TenantDeboardingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Initiate New Deboarding')
                ->icon('heroicon-o-arrow-left-on-rectangle'),
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Deboardings'),

            'active_notices' => Tab::make('Notice Served')
                ->badge(fn () => TenantDeboarding::where('status', DeboardingStatus::NOTICE_SERVED)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DeboardingStatus::NOTICE_SERVED)),

            'audit_pending' => Tab::make('Audit Pending / In Progress')
                ->badge(fn () => TenantDeboarding::whereIn('status', [
                    DeboardingStatus::AUDIT_PENDING,
                    DeboardingStatus::AUDIT_IN_PROGRESS,
                    DeboardingStatus::AUDIT_REVIEW,
                ])->count())
                ->badgeColor('info')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    DeboardingStatus::AUDIT_PENDING,
                    DeboardingStatus::AUDIT_IN_PROGRESS,
                    DeboardingStatus::AUDIT_REVIEW,
                ])),

            'maintenance' => Tab::make('Maintenance / Repairs')
                ->badge(fn () => TenantDeboarding::where('status', DeboardingStatus::MAINTENANCE_REQUIRED)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DeboardingStatus::MAINTENANCE_REQUIRED)),

            'settlement_pending' => Tab::make('Keys & Settlement')
                ->badge(fn () => TenantDeboarding::whereIn('status', [
                    DeboardingStatus::AUDIT_APPROVED,
                    DeboardingStatus::SETTLEMENT_PENDING,
                ])->count())
                ->badgeColor('amber')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', [
                    DeboardingStatus::AUDIT_APPROVED,
                    DeboardingStatus::SETTLEMENT_PENDING,
                ])),

            'completed' => Tab::make('Completed / Vacated')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', DeboardingStatus::COMPLETED)),
        ];
    }
}
