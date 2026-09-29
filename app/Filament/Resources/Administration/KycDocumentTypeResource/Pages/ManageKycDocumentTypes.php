<?php

namespace App\Filament\Resources\Administration\KycDocumentTypeResource\Pages;

use App\Filament\Resources\Administration\KycDocumentTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageKycDocumentTypes extends ManageRecords
{
    protected static string $resource = KycDocumentTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Document Type')
                ->icon('heroicon-o-plus')
                ->modalWidth('3xl'),
        ];
    }
}
