<?php

namespace App\Filament\Resources\Properties\Pages;


use App\Domain\Property\Models\Property;

use App\Filament\Resources\Properties\PropertyResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [

            DeleteAction::make(),
        ];
    }

    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    public function getContentTabLabel(): ?string
    {
        return 'Basic Details';
    }

    public function getContentTabIcon(): ?string
    {
        return 'heroicon-o-information-circle';
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
