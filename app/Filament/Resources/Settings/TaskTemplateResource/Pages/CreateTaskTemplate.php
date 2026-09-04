<?php

namespace App\Filament\Resources\Settings\TaskTemplateResource\Pages;

use App\Filament\Resources\Settings\TaskTemplateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTaskTemplate extends CreateRecord
{
    protected static string $resource = TaskTemplateResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
