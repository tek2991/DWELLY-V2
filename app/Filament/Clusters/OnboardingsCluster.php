<?php

namespace App\Filament\Clusters;

use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;

class OnboardingsCluster extends Cluster
{
    protected static \BackedEnum|string|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Onboardings';

    protected static \UnitEnum|string|null $navigationGroup = 'Properties & Leasing';

    protected static ?int $navigationSort = 3;

    protected static ?string $slug = 'onboardings';
}
