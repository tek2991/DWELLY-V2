<?php

namespace App\Filament\Resources\Operations\TaskResource\Pages;

use App\Domain\Shared\Services\NumberingService;
use App\Domain\Task\Enums\TaskStatus;
use App\Filament\Resources\Operations\TaskResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTask extends CreateRecord
{
    protected static string $resource = TaskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['task_number'] = $data['task_number'] ?? NumberingService::generate('task');
        $data['created_by_id'] = auth()->id();
        $data['status'] = $data['status'] ?? TaskStatus::PENDING->value;

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->getRecord()]);
    }
}
