<?php

namespace App\Domain\Party\Services;

use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\OwnerProfile;
use App\Domain\Party\Models\TenantProfile;
use App\Domain\Party\Models\VendorProfile;
use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Party\Events\PartyCreated;
use App\Domain\Party\Events\PartyUpdated;
use Illuminate\Support\Facades\DB;

class PartyService
{
    public function __construct(
        private AccountingProvisioningService $accountingProvisioning
    ) {}

    public function createParty(array $partyData, array $roles, array $profileData = []): Party
    {
        return DB::transaction(function () use ($partyData, $roles, $profileData) {
            $individualData = $partyData['individual_data'] ?? [];
            $organizationData = $partyData['organization_data'] ?? [];
            $bankDetails = $partyData['bank_details'] ?? [];
            $addressDetails = $partyData['address_details'] ?? [];
            
            unset($partyData['individual_data'], $partyData['organization_data'], $partyData['bank_details'], $partyData['address_details'], $partyData['is_bank_editing_unlocked']);
            
            $party = Party::create($partyData);

            // Create individual or organization record depending on party_type
            if ($party->party_type === 'individual') {
                $party->individual()->create($individualData);
            } else {
                $party->organization()->create($organizationData);
            }

            if (!empty(array_filter($bankDetails))) {
                $party->bankAccounts()->create([
                    'beneficiary_name' => $bankDetails['beneficiary_name'] ?? null,
                    'bank_name' => $bankDetails['bank_name'] ?? null,
                    'bank_address' => $bankDetails['bank_address'] ?? null,
                    'account_number' => $bankDetails['account_number'] ?? null,
                    'ifsc_code' => $bankDetails['ifsc_code'] ?? null,
                    'is_primary' => true,
                ]);
            }

            if (!empty($addressDetails['primary_address'])) {
                $party->addresses()->create([
                    'type' => $party->party_type === 'individual' ? 'residential' : 'registered_office',
                    'address_line_1' => $addressDetails['primary_address'],
                    'is_primary' => true,
                ]);
            }

            if (!empty($addressDetails['billing_address'])) {
                $party->addresses()->create([
                    'type' => 'billing',
                    'address_line_1' => $addressDetails['billing_address'],
                    'is_primary' => false,
                ]);
            }

            if (!empty($addressDetails['shipping_address'])) {
                $party->addresses()->create([
                    'type' => 'shipping',
                    'address_line_1' => $addressDetails['shipping_address'],
                    'is_primary' => false,
                ]);
            }

            // Create specific profiles
            foreach ($roles as $role) {
                match ($role) {
                    'owner' => OwnerProfile::create(['party_id' => $party->id] + $profileData),
                    'tenant' => TenantProfile::create(['party_id' => $party->id] + $profileData),
                    'vendor' => VendorProfile::create(['party_id' => $party->id] + $profileData),
                    default => throw new \InvalidArgumentException("Invalid role type: {$role}"),
                };
            }
            
            // Reload relationships to ensure fresh data is synced
            $party->loadMissing(['individual', 'organization', 'bankAccounts', 'addresses', 'ownerProfile', 'tenantProfile', 'vendorProfile']);

            // Provision Accounting Entities Synchronously within the Transaction
            $this->accountingProvisioning->ensurePartyAccountingReady($party);

            // Dispatch event for secondary systems (notifications, etc)
            $primaryRole = $roles[0] ?? 'owner';
            PartyCreated::dispatch($party, $primaryRole);

            return $party;
        });
    }

    public function updateParty(Party $party, array $data): Party
    {
        return DB::transaction(function () use ($party, $data) {
            $individualData = $data['individual_data'] ?? null;
            $organizationData = $data['organization_data'] ?? null;
            $bankDetails = $data['bank_details'] ?? null;
            $addressDetails = $data['address_details'] ?? null;
            $roles = $data['roles'] ?? null;
            
            unset($data['individual_data'], $data['organization_data'], $data['profile_type'], $data['roles'], $data['bank_details'], $data['address_details'], $data['is_bank_editing_unlocked']);
            
            $party->update($data);

            if ($party->party_type === 'individual' && $individualData) {
                $party->individual()->updateOrCreate(['party_id' => $party->id], $individualData);
            } elseif ($party->party_type === 'organization' && $organizationData) {
                $party->organization()->updateOrCreate(['party_id' => $party->id], $organizationData);
            }

            if ($bankDetails && !empty(array_filter($bankDetails))) {
                $party->bankAccounts()->updateOrCreate(
                    ['party_id' => $party->id, 'is_primary' => true],
                    [
                        'beneficiary_name' => $bankDetails['beneficiary_name'] ?? null,
                        'bank_name' => $bankDetails['bank_name'] ?? null,
                        'bank_address' => $bankDetails['bank_address'] ?? null,
                        'account_number' => $bankDetails['account_number'] ?? null,
                        'ifsc_code' => $bankDetails['ifsc_code'] ?? null,
                    ]
                );
            }

            if ($addressDetails) {
                if (isset($addressDetails['primary_address'])) {
                    $primaryType = $party->party_type === 'individual' ? 'residential' : 'registered_office';
                    if (!empty($addressDetails['primary_address'])) {
                        $party->addresses()->updateOrCreate(
                            ['party_id' => $party->id, 'type' => $primaryType],
                            ['address_line_1' => $addressDetails['primary_address'], 'is_primary' => true]
                        );
                    } else {
                        $party->addresses()->where('type', $primaryType)->delete();
                    }
                }
                if (isset($addressDetails['billing_address'])) {
                    if (!empty($addressDetails['billing_address'])) {
                        $party->addresses()->updateOrCreate(
                            ['party_id' => $party->id, 'type' => 'billing'],
                            ['address_line_1' => $addressDetails['billing_address'], 'is_primary' => false]
                        );
                    } else {
                        $party->addresses()->where('type', 'billing')->delete();
                    }
                }
                if (isset($addressDetails['shipping_address'])) {
                    if (!empty($addressDetails['shipping_address'])) {
                        $party->addresses()->updateOrCreate(
                            ['party_id' => $party->id, 'type' => 'shipping'],
                            ['address_line_1' => $addressDetails['shipping_address'], 'is_primary' => false]
                        );
                    } else {
                        $party->addresses()->where('type', 'shipping')->delete();
                    }
                }
            }

            // Handle roles synchronization
            if ($roles !== null) {
                // Determine roles to add
                if (in_array('owner', $roles) && !$party->ownerProfile()->exists()) {
                    OwnerProfile::create(['party_id' => $party->id]);
                }
                if (in_array('tenant', $roles) && !$party->tenantProfile()->exists()) {
                    TenantProfile::create(['party_id' => $party->id]);
                }
                if (in_array('vendor', $roles) && !$party->vendorProfile()->exists()) {
                    VendorProfile::create(['party_id' => $party->id]);
                }
                
                // Determine roles to remove (optional: typically we don't delete roles silently, but for UI sync we should)
                if (!in_array('owner', $roles) && $party->ownerProfile()->exists()) {
                    $party->ownerProfile()->delete();
                }
                if (!in_array('tenant', $roles) && $party->tenantProfile()->exists()) {
                    $party->tenantProfile()->delete();
                }
                if (!in_array('vendor', $roles) && $party->vendorProfile()->exists()) {
                    $party->vendorProfile()->delete();
                }
            }

            // Determine the primary profile type for the event
            $profileType = $roles[0] ?? 'owner';
            if ($party->vendorProfile()->exists()) $profileType = 'vendor';
            elseif ($party->tenantProfile()->exists()) $profileType = 'tenant';
            
            // Reload relationships to ensure fresh data is synced
            $party->load(['individual', 'organization', 'bankAccounts', 'addresses', 'ownerProfile', 'tenantProfile', 'vendorProfile']);

            // Synchronize Accounting entities with updated details
            $this->accountingProvisioning->ensurePartyAccountingReady($party);

            // Dispatch event for secondary integrations
            PartyUpdated::dispatch($party, $profileType);

            return $party;
        });
    }
}
