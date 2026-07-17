<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class AdministrationCluster extends Cluster
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-shield-check';
    
    protected static ?string $navigationLabel = 'Administration';
    
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    
    protected static ?string $slug = 'administration';
}
