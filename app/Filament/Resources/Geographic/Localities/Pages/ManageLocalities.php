<?php

namespace App\Filament\Resources\Geographic\Localities\Pages;

use App\Filament\Resources\Geographic\Localities\LocalityResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageLocalities extends ManageRecords
{
    protected static string $resource = LocalityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
