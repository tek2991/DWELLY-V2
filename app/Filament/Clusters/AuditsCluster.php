<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class AuditsCluster extends Cluster
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Audits & Inspections';

    protected static \UnitEnum|string|null $navigationGroup = 'Maintenance & Field Ops';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'audits';
}
