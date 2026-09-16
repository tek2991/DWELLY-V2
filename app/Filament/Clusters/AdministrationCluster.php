<?php

namespace App\Filament\Clusters;

use App\Domain\Auth\Enums\RoleName;
use Filament\Clusters\Cluster;

class AdministrationCluster extends Cluster
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Administration';

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $slug = 'administration';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        if ($user->roles->isEmpty()) {
            return true;
        }

        return $user->can('admin.users.viewAny')
            || $user->can('admin.audit_logs.viewAny')
            || $user->hasAnyRole([RoleName::BUSINESS_OWNER, RoleName::CITY_MANAGER, RoleName::ACCOUNTANT]);
    }
}
