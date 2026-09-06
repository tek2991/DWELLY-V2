<?php

namespace App\Filament\Clusters\ReferenceData;

use App\Domain\Auth\Enums\RoleName;
use BackedEnum;
use Filament\Clusters\Cluster;

class ReferenceDataCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?string $navigationLabel = 'Reference Data';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $slug = 'reference-data';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        return $user->can('admin.masters.viewAny')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::ACCOUNTANT]);
    }
}
