<?php

namespace App\Filament\Resources\Parties\Pages;

use App\Filament\Resources\Parties\PartyResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use App\Domain\Party\Actions\CreatePartyAction;
use Illuminate\Database\Eloquent\Model;

class CreateParty extends CreateRecord
{
    protected static string $resource = PartyResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        // Extract profile type from form data
        $profileType = $data['profile_type'] ?? 'owner';
        unset($data['profile_type']);

        $action = app(CreatePartyAction::class);
        return $action->execute($data, $profileType, []);
    }
}
