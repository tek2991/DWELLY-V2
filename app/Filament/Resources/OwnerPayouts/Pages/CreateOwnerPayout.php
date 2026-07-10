<?php

namespace App\Filament\Resources\OwnerPayouts\Pages;

use App\Filament\Resources\OwnerPayouts\OwnerPayoutResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOwnerPayout extends CreateRecord
{
    protected static string $resource = OwnerPayoutResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
