<?php

namespace App\Domain\Party\Actions;

use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\OwnerProfile;
use App\Domain\Party\Models\TenantProfile;
use App\Domain\Party\Models\VendorProfile;
use App\Domain\Finance\Services\AccountingBridgeService;
use Illuminate\Support\Facades\DB;

class CreatePartyAction
{
    public function __construct(private AccountingBridgeService $accounting) {}

    public function execute(array $partyData, string $profileType, array $profileData = []): Party
    {
        return DB::transaction(function () use ($partyData, $profileType, $profileData) {
            $party = Party::create($partyData);

            // Create individual or organization record depending on party_type
            if ($party->party_type === 'individual') {
                $party->individual()->create($partyData['individual_data'] ?? []);
            } else {
                $party->organization()->create($partyData['organization_data'] ?? []);
            }

            // Create specific profile
            match ($profileType) {
                'owner' => OwnerProfile::create(['party_id' => $party->id] + $profileData),
                'tenant' => TenantProfile::create(['party_id' => $party->id] + $profileData),
                'vendor' => VendorProfile::create(['party_id' => $party->id] + $profileData),
                default => throw new \InvalidArgumentException("Invalid profile type: {$profileType}"),
            };

            // Dispatch event to queue accounting contact creation
            \App\Domain\Party\Events\PartyCreated::dispatch($party, $profileType);

            return $party;
        });
    }
}
