<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Services\OpportunityReadinessService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class OpportunityReadinessServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_opportunity_with_only_mandatory_fields_is_ready_for_mou()
    {
        $user = User::factory()->create();

        $opportunity = new Opportunity([
            'title' => 'Test Opportunity',
            'owner_name' => 'John Doe',
            'owner_phone' => '1234567890',
            'assigned_user_id' => $user->id,
        ]);

        $service = new OpportunityReadinessService();
        $result = $service->canCreateMOU($opportunity);

        $this->assertTrue($result['is_ready']);
        $this->assertEmpty($result['errors']);
    }

    public function test_opportunity_missing_mandatory_fields_fails_readiness()
    {
        $opportunity = new Opportunity([
            'title' => 'Test Opportunity',
            'owner_name' => 'John Doe',
            // Missing owner_phone and assigned_user_id
        ]);

        $service = new OpportunityReadinessService();
        $result = $service->canCreateMOU($opportunity);

        $this->assertFalse($result['is_ready']);
        $this->assertNotEmpty($result['errors']);
    }
}
