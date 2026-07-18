<?php

namespace App\Filament\Resources\Settings\UtilityProviderResource\Pages;

use App\Filament\Resources\Settings\UtilityProviderResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageUtilityProviders extends ManageRecords
{
    protected static string $resource = UtilityProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
