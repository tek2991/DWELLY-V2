<?php

namespace Tek2991\Accounting\Filament\Resources\Settings\Organizations\Pages;

use Tek2991\Accounting\Filament\Resources\Settings\Organizations\OrganizationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOrganizations extends ListRecords
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
