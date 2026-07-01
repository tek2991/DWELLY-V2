<?php

namespace App\Filament\Pages\Settings;

use Filament\Pages\Page;
use Illuminate\Support\Facades\Gate;

class ReferenceData extends Page
{
    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-cog-6-tooth';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function getNavigationSort(): ?int
    {
        return 1;
    }
    protected string $view = 'filament.pages.settings.reference-data';

    /**
     * Define module-level authorization for Settings.
     */
    public static function canAccess(): bool
    {
        // Require administration.access for the settings group
        return Gate::allows('administration.access');
    }
}
