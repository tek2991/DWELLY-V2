<?php

namespace App\Filament\Clusters;

use App\Domain\Auth\Enums\RoleName;
use Filament\Clusters\Cluster;

class GeographicCluster extends Cluster
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Geographic';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $slug = 'geographic';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        return $user->can('admin.geographic.viewAny')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER]);
    }
}
