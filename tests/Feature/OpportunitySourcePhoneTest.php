<?php

namespace Tests\Feature;

use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Models\OpportunitySource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OpportunitySourcePhoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_opportunity_can_be_created_with_source_phone(): void
    {
        $user = User::factory()->create();
        $source = OpportunitySource::create([
            'name' => 'Broker Channel',
            'slug' => 'broker-channel',
            'is_active' => true,
        ]);

        $opportunityData = [
            'number' => 'OPP-TEST-001',
            'title' => 'Sample Luxury Villa Lead',
            'status' => OpportunityStatus::NEW,
            'opportunity_source_id' => $source->id,
            'source_phone' => '+919876543210',
            'owner_name' => 'John Doe',
            'owner_phone' => '+919123456789',
            'assigned_user_id' => $user->id,
        ];

        $opportunity = Opportunity::create($opportunityData);

        $this->assertDatabaseHas('opportunities', [
            'id' => $opportunity->id,
            'title' => 'Sample Luxury Villa Lead',
            'opportunity_source_id' => $source->id,
            'source_phone' => '+919876543210',
            'owner_phone' => '+919123456789',
        ]);
    }

    public function test_opportunity_source_phone_can_be_updated(): void
    {
        $user = User::factory()->create();
        $opportunity = Opportunity::create([
            'number' => 'OPP-TEST-002',
            'title' => 'Commercial Space',
            'status' => OpportunityStatus::NEW,
            'owner_name' => 'Alice Smith',
            'owner_phone' => '+919888877777',
            'assigned_user_id' => $user->id,
        ]);

        $opportunity->update([
            'source_phone' => '+919999900000',
        ]);

        $this->assertEquals('+919999900000', $opportunity->fresh()->source_phone);
    }
}
