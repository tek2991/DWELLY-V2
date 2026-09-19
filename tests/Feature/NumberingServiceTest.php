<?php

namespace Tests\Feature;

use App\Domain\Agreement\Actions\DraftTenancyAgreementAction;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Mou\Models\Mou;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Shared\Models\NumberingSequence;
use App\Domain\Shared\Services\NumberingService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NumberingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_generates_sequential_tenancy_codes(): void
    {
        $year = Carbon::now()->year;

        $code1 = NumberingService::generate('tenancy');
        $code2 = NumberingService::generate('tenancy');
        $code3 = NumberingService::generate('tenancy');

        $this->assertEquals("TNC-{$year}-00001", $code1);
        $this->assertEquals("TNC-{$year}-00002", $code2);
        $this->assertEquals("TNC-{$year}-00003", $code3);
    }

    public function test_it_skips_existing_codes_when_sequence_is_out_of_sync(): void
    {
        $year = Carbon::now()->year;

        // Pre-populate records with codes 00001, 00002, 00003
        $property = Property::create([
            'building_name' => 'Test Building',
            'address_line_1' => 'Test Address',
            'status' => 'vacant',
        ]);

        TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => "TNC-{$year}-00001",
            'status' => 'active',
            'rent_amount' => 10000,
            'security_deposit' => 20000,
        ]);

        TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => "TNC-{$year}-00002",
            'status' => 'active',
            'rent_amount' => 10000,
            'security_deposit' => 20000,
        ]);

        // Sequence starts at 0, so next would be 00001, but 00001 and 00002 exist
        $generatedCode = NumberingService::generate('tenancy');

        $this->assertEquals("TNC-{$year}-00003", $generatedCode);

        // Next call should give 00004
        $nextCode = NumberingService::generate('tenancy');
        $this->assertEquals("TNC-{$year}-00004", $nextCode);
    }

    public function test_it_skips_arbitrary_gaps_in_existing_codes(): void
    {
        $year = Carbon::now()->year;

        $property = Property::create([
            'building_name' => 'Gap Building',
            'address_line_1' => 'Gap Address',
            'status' => 'vacant',
        ]);

        // Sequence at 2, but code 00003 already exists
        NumberingSequence::create([
            'entity_type' => 'tenancy',
            'prefix' => 'TNC',
            'pad_length' => 5,
            'include_year' => true,
            'year' => $year,
            'last_sequence' => 2,
        ]);

        TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => "TNC-{$year}-00003",
            'status' => 'active',
            'rent_amount' => 10000,
            'security_deposit' => 20000,
        ]);

        $generatedCode = NumberingService::generate('tenancy');

        // Should skip 00003 and return 00004
        $this->assertEquals("TNC-{$year}-00004", $generatedCode);
    }

    public function test_draft_tenancy_agreement_action_succeeds_when_sequence_was_behind(): void
    {
        $year = Carbon::now()->year;
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Action Building',
            'address_line_1' => 'Action Address',
            'status' => 'vacant',
        ]);

        $tenant = Party::create([
            'display_name' => 'Action Tenant',
            'party_type' => 'individual',
        ]);

        // Pre-create an agreement with code 00001
        TenancyAgreement::create([
            'property_id' => $property->id,
            'code' => "TNC-{$year}-00001",
            'status' => 'draft',
            'rent_amount' => 12000,
            'security_deposit' => 24000,
        ]);

        // Sequence is at 0 (or not created yet)
        $action = app(DraftTenancyAgreementAction::class);
        $agreement = $action->execute(
            $property,
            [
                'rent_amount' => 15000,
                'security_deposit' => 30000,
                'start_date' => '2026-10-01',
                'end_date' => '2027-09-30',
            ],
            [
                [
                    'party_id' => $tenant->id,
                    'role_type' => 'Primary Tenant',
                    'is_primary' => true,
                ],
            ],
            $user
        );

        $this->assertNotNull($agreement);
        $this->assertEquals("TNC-{$year}-00002", $agreement->code);
    }

    public function test_it_resets_sequence_on_new_year(): void
    {
        $lastYear = Carbon::now()->year - 1;
        $currentYear = Carbon::now()->year;

        NumberingSequence::create([
            'entity_type' => 'tenancy',
            'prefix' => 'TNC',
            'pad_length' => 5,
            'include_year' => true,
            'year' => $lastYear,
            'last_sequence' => 99,
        ]);

        $code = NumberingService::generate('tenancy');

        $this->assertEquals("TNC-{$currentYear}-00001", $code);

        $seq = NumberingSequence::where('entity_type', 'tenancy')->first();
        $this->assertEquals($currentYear, $seq->year);
        $this->assertEquals(1, $seq->last_sequence);
    }

    public function test_mou_generation_skips_soft_deleted_and_active_collisions(): void
    {
        $year = Carbon::now()->year;

        $opportunity = \App\Domain\Opportunity\Models\Opportunity::create([
            'title' => 'Test Opportunity',
            'number' => 'OPP-001',
            'owner_name' => 'John MOU Owner',
            'owner_phone' => '9998887776',
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::NEW,
        ]);

        $mou = Mou::create([
            'opportunity_id' => $opportunity->id,
            'number' => "MOU-{$year}-00001",
            'type' => \App\Domain\Mou\Enums\MouType::ONBOARDING,
            'status' => \App\Domain\Opportunity\Enums\MouStatus::DRAFT,
            'version' => 1,
            'start_date' => '2026-01-01',
        ]);

        // Soft-delete the MOU
        $mou->delete();

        // Even though it is soft-deleted, it must not collide
        $code = NumberingService::generate('mou');
        $this->assertEquals("MOU-{$year}-00002", $code);
    }
}
