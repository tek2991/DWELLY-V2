<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\OwnerProfile;
use App\Domain\Party\Models\TenantProfile;
use App\Domain\Party\Models\VendorProfile;
use Illuminate\Support\Facades\DB;

class CreatePartyAction
{
    public function __construct(private \App\Domain\Party\Services\PartyService $partyService) {}

    public function execute(array $partyData, array $roles, array $profileData = []): Party
    {
        return $this->partyService->createParty($partyData, $roles, $profileData);
    }
}
