<?php

namespace App\Filament\Resources\Properties\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Model;

class OnboardingProgressWidget extends Widget
{
    protected string $view = 'filament.resources.properties.pages.onboarding-dashboard';

    public ?Model $record = null;
    
    // Filament injects the record into widgets on the record page if defined as a public property
    
    protected int | string | array $columnSpan = 'full';
}
