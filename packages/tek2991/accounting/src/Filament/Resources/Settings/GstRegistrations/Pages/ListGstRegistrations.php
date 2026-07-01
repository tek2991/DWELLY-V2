<?php

namespace Tek2991\Accounting\Filament\Resources\Settings\GstRegistrations\Pages;

use Tek2991\Accounting\Filament\Resources\Settings\GstRegistrations\GstRegistrationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGstRegistrations extends ListRecords
{
    protected static string $resource = GstRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
