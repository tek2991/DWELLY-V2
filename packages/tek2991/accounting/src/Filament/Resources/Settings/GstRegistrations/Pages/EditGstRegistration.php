<?php

namespace Tek2991\Accounting\Filament\Resources\Settings\GstRegistrations\Pages;

use Tek2991\Accounting\Filament\Resources\Settings\GstRegistrations\GstRegistrationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGstRegistration extends EditRecord
{
    protected static string $resource = GstRegistrationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
