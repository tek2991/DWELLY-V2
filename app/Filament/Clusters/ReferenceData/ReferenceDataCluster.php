<?php

namespace App\Filament\Clusters\ReferenceData;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class ReferenceDataCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';
    
    protected static ?string $navigationLabel = 'Reference Data';
    
    protected static \UnitEnum|string|null $navigationGroup = 'Settings';
    
    protected static ?string $slug = 'reference-data';
}
