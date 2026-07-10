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
        // Extract roles from form data
        $roles = $data['roles'] ?? ['owner'];
        unset($data['roles']);

        $action = app(CreatePartyAction::class);
        return $action->execute($data, $roles, []);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
