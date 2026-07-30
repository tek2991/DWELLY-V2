<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;

class AuditsCluster extends Cluster
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Audits';

    protected static \UnitEnum|string|null $navigationGroup = 'Portfolio & Operations';

    protected static ?string $slug = 'audits';
}
