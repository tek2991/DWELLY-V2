<?php

namespace App\Filament\Resources\Settings\TaskTemplateResource\Pages;

use App\Filament\Resources\Settings\TaskTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaskTemplates extends ListRecords
{
    protected static string $resource = TaskTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
