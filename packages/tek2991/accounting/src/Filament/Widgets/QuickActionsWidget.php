<?php

namespace Tek2991\Accounting\Filament\Widgets;

use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected string $view = 'accounting::filament.widgets.quick-actions';

    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = 'full';
}
