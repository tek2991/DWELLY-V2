<?php

namespace Tek2991\Accounting\Filament\Resources\Settings\Organizations\Pages;

use Tek2991\Accounting\Filament\Resources\Settings\Organizations\OrganizationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOrganization extends EditRecord
{
    protected static string $resource = OrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
