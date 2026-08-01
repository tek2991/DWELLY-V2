<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class OperationsQuickActionsWidget extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.operations-quick-actions';

    protected int | string | array $columnSpan = 'full';
}
