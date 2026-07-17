<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class GeographicCluster extends Cluster
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-globe-alt';
    
    protected static ?string $navigationLabel = 'Geographic';
    
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    
    protected static ?string $slug = 'geographic';
}
