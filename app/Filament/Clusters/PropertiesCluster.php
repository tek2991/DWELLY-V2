<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class PropertiesCluster extends Cluster
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationLabel = 'Properties';

    protected static \UnitEnum|string|null $navigationGroup = 'Portfolio & Operations';

    protected static ?string $slug = 'properties';
}
