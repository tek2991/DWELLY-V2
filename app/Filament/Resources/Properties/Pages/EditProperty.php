<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Domain\Implementation\Models\ImplementationProject;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Operations\ImplementationProjectResource;
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
            Action::make('workspace')
                ->label('Onboarding Workspace')
                ->icon('heroicon-m-clipboard-document-check')
                ->color('primary')
                ->url(function (Property $record): ?string {
                    $project = ImplementationProject::where('entity_type', Property::class)
                        ->where('entity_id', $record->id)
                        ->first();
                        
                    return $project ? ImplementationProjectResource::getUrl('workspace', ['record' => $project]) : null;
                })
                ->visible(fn (Property $record): bool => ImplementationProject::where('entity_type', Property::class)
                    ->where('entity_id', $record->id)
                    ->exists()),
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
}
