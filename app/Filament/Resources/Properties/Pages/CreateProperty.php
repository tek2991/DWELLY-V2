<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use Filament\Resources\Pages\CreateRecord;
use App\Domain\Property\Actions\OnboardPropertyAction;
use Illuminate\Database\Eloquent\Model;

class CreateProperty extends CreateRecord
{
    protected static string $resource = PropertyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Resolve the action from the container and execute it
        $action = app(OnboardPropertyAction::class);
        return $action->execute($data, auth()->user());
    }
}
