<?php

namespace App\Filament\Resources\OwnerPayouts\Pages;

use App\Filament\Resources\OwnerPayouts\OwnerPayoutResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOwnerPayout extends EditRecord
{
    protected static string $resource = OwnerPayoutResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
