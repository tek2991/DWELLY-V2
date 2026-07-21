<?php

namespace Tests\Feature;

use App\Domain\Mou\Models\Mou;
use App\Domain\Mou\Services\MouService;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MouSignatoryDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_mou_from_opportunity_populates_signatory_details_with_relation_self()
    {
        $user = User::factory()->create();

        $opportunity = Opportunity::create([
            'number' => 'OPP-500',
            'title' => 'Test Opportunity for Signatory',
            'owner_name' => 'Owner Person',
            'owner_phone' => '9876543210',
            'owner_email' => 'owner@example.com',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::READY_FOR_MOU,
        ]);

        $service = app(MouService::class);
        $mou = $service->createDraftFromOpportunity($opportunity);

        $this->assertFalse($mou->is_signatory_different);
        $this->assertEquals('Owner Person', $mou->owner_details['name']);
        $this->assertEquals('9876543210', $mou->owner_details['phone']);
        $this->assertEquals('Owner Person', $mou->signatory_details['name']);
        $this->assertEquals('Self', $mou->signatory_details['relation']);
        $this->assertEquals('9876543210', $mou->signatory_details['phone']);
        $this->assertEquals('owner@example.com', $mou->signatory_details['email']);
    }

    public function test_resolving_party_when_signatory_is_not_different_syncs_signatory_details_from_party()
    {
        $user = User::factory()->create();

        $opportunity = Opportunity::create([
            'number' => 'OPP-501',
            'title' => 'Test Opportunity 501',
            'owner_name' => 'Owner Person',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::READY_FOR_MOU,
        ]);

        $service = app(MouService::class);
        $mou = $service->createDraftFromOpportunity($opportunity);

        $service->resolveParty($mou, [
            'action_type' => 'create_new',
            'party_type' => 'individual',
            'name' => 'Verified Party Name',
            'phone' => '9876543210',
            'email' => 'verified@example.com',
            'pan_number' => 'ABCDE1234F',
            'aadhar_number' => '123456789012',
            'address' => '123 Street',
        ]);

        $mou->refresh();

        $this->assertFalse($mou->is_signatory_different);
        $this->assertEquals('Verified Party Name', $mou->signatory_details['name']);
        $this->assertEquals('Self', $mou->signatory_details['relation']);
        $this->assertEquals('ABCDE1234F', $mou->signatory_details['pan_number']);
        $this->assertEquals('123456789012', $mou->signatory_details['aadhar_number']);
    }

    public function test_resolving_party_when_signatory_is_different_preserves_custom_signatory_details()
    {
        $user = User::factory()->create();

        $opportunity = Opportunity::create([
            'number' => 'OPP-502',
            'title' => 'Test Opportunity 502',
            'owner_name' => 'Owner Person',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::READY_FOR_MOU,
        ]);

        $service = app(MouService::class);
        $mou = $service->createDraftFromOpportunity($opportunity);

        $mou->update([
            'is_signatory_different' => true,
            'signatory_details' => [
                'name' => 'POA Representative',
                'relation' => 'POA Holder',
                'phone' => '1112223333',
                'email' => 'poa@example.com',
                'aadhar_number' => '999988887777',
                'pan_number' => 'XYZPR5678Q',
            ],
        ]);

        $service->resolveParty($mou, [
            'action_type' => 'create_new',
            'party_type' => 'individual',
            'name' => 'Actual Owner',
            'phone' => '9876543210',
            'email' => 'owner@example.com',
            'address' => '456 Avenue',
        ]);

        $mou->refresh();

        $this->assertTrue($mou->is_signatory_different);
        $this->assertEquals('POA Representative', $mou->signatory_details['name']);
        $this->assertEquals('POA Holder', $mou->signatory_details['relation']);
        $this->assertEquals('1112223333', $mou->signatory_details['phone']);
    }
}
