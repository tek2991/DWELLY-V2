<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenancyRole;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Finance\Services\OwnerPayoutService;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Billing\BulkGenerateOwnerPayouts;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class OwnerPayoutDraftAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branch;
    protected User $user;
    protected Party $owner;
    protected Party $tenant;
    protected Property $property;
    protected TenancyAgreement $agreement;
    protected OwnerPayoutService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DefaultChartOfAccountsSeeder::class);

        $org = Organization::create([
            'name' => 'Dwelly Living Private Limited',
            'legal_name' => 'Dwelly Living Private Limited',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Main HQ Branch',
            'code' => 'HQ-01',
            'is_active' => true,
        ]);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->owner = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Dr. Sharma (Owner)',
            'email' => 'sharma@example.com',
            'phone' => '9876543210',
        ]);
        $this->owner->ownerProfile()->create([]);
        $this->owner->individual()->create(['name' => 'Dr. Sharma', 'pan_number' => 'ABCDE1234F']);

        $this->tenant = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Rahul Verma (Tenant)',
            'email' => 'rahul@example.com',
            'phone' => '9876543211',
        ]);
        $this->tenant->tenantProfile()->create([]);
        $this->tenant->individual()->create(['name' => 'Rahul Verma', 'pan_number' => 'FGHIJ5678K']);

        $this->property = Property::create([
            'building_name' => 'Magnolia Residences 402',
            'code' => 'PROP-MAG-402',
            'status' => 'occupied',
        ]);

        $opp = \App\Domain\Opportunity\Models\Opportunity::create([
            'number' => 'OPP-2026-MAG',
            'title' => 'Magnolia Residences Lead',
            'status' => 'converted',
        ]);

        \App\Domain\Mou\Models\Mou::create([
            'number' => 'MOU-2026-MAG',
            'property_id' => $this->property->id,
            'opportunity_id' => $opp->id,
            'party_id' => $this->owner->id,
            'type' => 'onboarding',
            'status' => 'verified',
        ]);

        $this->agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'AGR-2026-MAG',
            'rent_amount' => 30000.00,
            'security_deposit' => 60000.00,
            'status' => 'active',
            'start_date' => '2026-08-01',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-08-01',
        ]);

        TenancyRole::create([
            'tenancy_agreement_id' => $this->agreement->id,
            'party_id' => $this->tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        $this->service = app(OwnerPayoutService::class);
    }

    /**
     * Test 1: Can save draft payout with custom adjustments and retrieve details.
     */
    public function test_can_save_draft_payout_with_custom_adjustments_and_retrieve_details(): void
    {
        $adjusted = [
            'rent_collected' => 28000.00,
            'management_fee_percent' => 8.0,
            'management_fee' => 2240.00,
            'advance_offset' => 500.00,
            'reserve_deduction' => 1000.00,
            'notes' => 'Negotiated special discount with owner for August.',
        ];

        $draft = $this->service->saveDraftPayout($this->property, 8, 2026, $adjusted, $this->user);

        $this->assertNotNull($draft);
        $this->assertEquals('draft', $draft->status);
        $this->assertEquals(28000.00, (float) $draft->rent_collected);
        $this->assertEquals(2240.00, (float) $draft->management_fee);
        $this->assertEquals(500.00, (float) $draft->advance_offset);
        $this->assertEquals(1000.00, (float) $draft->reserve_deduction);
        $this->assertEquals(24260.00, (float) $draft->amount); // 28000 - 2240 - 500 - 1000
        $this->assertEquals('Negotiated special discount with owner for August.', $draft->notes);

        // Verify calculatePayoutDetails uses the draft
        $details = $this->service->calculatePayoutDetails($this->property, 8, 2026);
        $this->assertTrue($details['is_adjusted']);
        $this->assertEquals($draft->id, $details['draft_payout_id']);
        $this->assertEquals(28000.00, $details['gross_rent']);
        $this->assertEquals(8.0, $details['management_fee_percent']);
        $this->assertEquals(2240.00, $details['management_fee']);
        $this->assertEquals(500.00, $details['advance_offset']);
        $this->assertEquals(1000.00, $details['reserve_deduction']);
        $this->assertEquals(24260.00, $details['net_payout']);
    }

    /**
     * Test 2: getBulkPayoutPreview reflects draft with amber badge and ready status.
     */
    public function test_get_bulk_payout_preview_shows_draft_with_adjusted_badge(): void
    {
        $this->service->saveDraftPayout($this->property, 8, 2026, [
            'rent_collected' => 25000.00,
            'management_fee_percent' => 5.0,
            'management_fee' => 1250.00,
        ], $this->user);

        $preview = $this->service->getBulkPayoutPreview(8, 2026);

        $this->assertEquals(1, $preview['summary']['ready_count']);
        $this->assertEquals(0, $preview['summary']['already_processed_count']);

        $item = $preview['items'][0];
        $this->assertEquals('ready', $item['status']);
        $this->assertTrue($item['is_adjusted']);
        $this->assertEquals('Ready • Adjusted', $item['status_label']);
        $this->assertEquals('amber', $item['badge_color']);
        $this->assertEquals(25000.00, $item['gross_rent']);
        $this->assertEquals(1250.00, $item['management_fee']);
        $this->assertEquals(23750.00, $item['net_payout']);
    }

    /**
     * Test 3: Can reset draft payout back to defaults.
     */
    public function test_can_reset_draft_payout_back_to_defaults(): void
    {
        $this->service->saveDraftPayout($this->property, 8, 2026, [
            'rent_collected' => 20000.00,
            'management_fee_percent' => 5.0,
        ], $this->user);

        $this->assertEquals(1, OwnerPayout::where('status', 'draft')->count());

        $reset = $this->service->resetDraftPayout($this->property, 8, 2026);
        $this->assertTrue($reset);
        $this->assertEquals(0, OwnerPayout::where('status', 'draft')->count());

        // Recalculated details should now reflect standard agreement rent (30000) & MOU fee (10%)
        $details = $this->service->calculatePayoutDetails($this->property, 8, 2026);
        $this->assertFalse($details['is_adjusted']);
        $this->assertNull($details['draft_payout_id']);
        $this->assertEquals(30000.00, $details['gross_rent']);
        $this->assertEquals(10.0, $details['management_fee_percent']);
        $this->assertEquals(3000.00, $details['management_fee']);
        $this->assertEquals(27000.00, $details['net_payout']);
    }

    /**
     * Test 4: Disbursing executes using draft numbers and transitions status to completed.
     */
    public function test_disbursement_uses_draft_numbers_and_transitions_status(): void
    {
        $draft = $this->service->saveDraftPayout($this->property, 8, 2026, [
            'rent_collected' => 25000.00,
            'management_fee_percent' => 5.0,
            'management_fee' => 1250.00,
            'notes' => 'Custom operator override',
        ], $this->user);

        $draftId = $draft->id;

        // Disburse via single property disbursement
        Livewire::test(BulkGenerateOwnerPayouts::class)
            ->set('month', 8)
            ->set('year', 2026)
            ->call('disburseSingleProperty', (string) $this->property->id);

        // Verify only 1 record exists and it was transitioned to completed
        $this->assertEquals(1, OwnerPayout::where('property_id', $this->property->id)->count());

        $completedPayout = OwnerPayout::find($draftId);
        $this->assertNotNull($completedPayout);
        $this->assertEquals('completed', $completedPayout->status);
        $this->assertEquals(25000.00, (float) $completedPayout->rent_collected);
        $this->assertEquals(1250.00, (float) $completedPayout->management_fee);
        $this->assertEquals(23750.00, (float) $completedPayout->amount);
        $this->assertNotNull($completedPayout->transaction_id);
        $this->assertNotNull($completedPayout->commission_invoice_id);
        $this->assertNotNull($completedPayout->processed_at);
        $this->assertEquals('Custom operator override', $completedPayout->notes);

        // Check preview now marks it as already processed
        $preview = $this->service->getBulkPayoutPreview(8, 2026);
        $this->assertEquals(0, $preview['summary']['ready_count']);
        $this->assertEquals(1, $preview['summary']['already_processed_count']);
    }

    /**
     * Test 5: Livewire component savePayoutAdjustment and resetPayoutAdjustment methods.
     */
    public function test_livewire_page_save_and_reset_adjustments(): void
    {
        Livewire::test(BulkGenerateOwnerPayouts::class)
            ->set('month', 8)
            ->set('year', 2026)
            ->call('savePayoutAdjustment', (string) $this->property->id, [
                'rent_collected' => 26000.00,
                'management_fee_percent' => 12.0,
                'management_fee' => 3120.00,
                'advance_offset' => 200.00,
                'reserve_deduction' => 0.0,
                'notes' => 'Adjustment saved via Livewire UI',
            ])
            ->assertHasNoErrors()
            ->assertSee('Ready • Adjusted');

        $draft = OwnerPayout::where('property_id', $this->property->id)->where('status', 'draft')->first();
        $this->assertNotNull($draft);
        $this->assertEquals(26000.00, (float) $draft->rent_collected);
        $this->assertEquals(3120.00, (float) $draft->management_fee);
        $this->assertEquals(22680.00, (float) $draft->amount);

        // Reset via Livewire
        Livewire::test(BulkGenerateOwnerPayouts::class)
            ->set('month', 8)
            ->set('year', 2026)
            ->call('resetPayoutAdjustment', (string) $this->property->id)
            ->assertHasNoErrors()
            ->assertSee('Ready to Disburse')
            ->assertDontSee('Ready • Adjusted');

        $this->assertEquals(0, OwnerPayout::where('status', 'draft')->count());
    }
}
