<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Services\TenancyDeboardingService;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenancyDeboardingTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Property $property;
    protected Party $tenant;
    protected Audit $moveInAudit;
    protected TenancyAgreement $agreement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->property = Property::create([
            'code' => 'PROP-TEST-001',
            'building_name' => 'Test Heights',
            'address_line_1' => '123 Test St',
            'city' => 'Guwahati',
            'state' => 'Assam',
            'pincode' => '781001',
            'status' => 'occupied',
        ]);

        $this->tenant = Party::create([
            'party_type' => 'individual',
            'display_name' => 'John Tenant',
            'phone' => '9876543210',
            'email' => 'john@example.com',
        ]);

        $this->moveInAudit = Audit::create([
            'property_id' => $this->property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::APPROVED,
            'inspector_id' => $this->user->id,
            'is_locked' => true,
        ]);

        $this->agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'audit_id' => $this->moveInAudit->id,
            'code' => 'TNC-2026-9999',
            'status' => 'active',
            'start_date' => now()->subMonths(6)->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'rent_amount' => 20000.00,
            'security_deposit' => 40000.00,
        ]);

        $this->agreement->roles()->create([
            'party_id' => $this->tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);
    }

    public function test_can_initiate_deboarding_and_trigger_move_out_audit(): void
    {
        $service = app(TenancyDeboardingService::class);

        $service->initiateDeboarding($this->agreement, [
            'notice_date' => now()->toDateString(),
            'vacating_date' => now()->addDays(30)->toDateString(),
            'deboarding_reason' => 'Agreement Expiry',
            'deboarding_notes' => 'Tenant moving to another city.',
        ]);

        $this->agreement->refresh();
        $this->assertEquals('deboarding_initiated', $this->agreement->status);
        $this->assertEquals('Agreement Expiry', $this->agreement->deboarding_reason);

        $moveOutAudit = $service->triggerMoveOutAudit($this->agreement, $this->user);

        $this->agreement->refresh();
        $this->assertNotNull($this->agreement->move_out_audit_id);
        $this->assertEquals($moveOutAudit->id, $this->agreement->move_out_audit_id);
        $this->assertEquals(AuditType::MOVE_OUT, $moveOutAudit->audit_type);
        $this->assertEquals($this->moveInAudit->id, $moveOutAudit->reference_audit_id);
    }

    public function test_can_complete_deboarding_and_vacate_property(): void
    {
        $service = app(TenancyDeboardingService::class);

        $service->initiateDeboarding($this->agreement, [
            'notice_date' => now()->toDateString(),
            'vacating_date' => now()->toDateString(),
            'deboarding_reason' => 'Mutual Agreement',
        ]);

        $moveOutAudit = $service->triggerMoveOutAudit($this->agreement, $this->user);
        $moveOutAudit->update(['status' => AuditStatus::APPROVED]);

        $service->completeDeboardingAndVacate(
            $this->agreement,
            'vacant',
            [
                'net_refund' => 35000.00,
                'settlement_status' => 'settled',
            ],
            $this->user
        );

        $this->agreement->refresh();
        $this->property->refresh();
        $moveOutAudit->refresh();

        $this->assertEquals('vacated', $this->agreement->status);
        $this->assertTrue($this->agreement->keys_returned);
        $this->assertEquals(35000.00, $this->agreement->net_deposit_refund);
        $this->assertEquals('vacant', $this->property->status);
        $this->assertTrue($moveOutAudit->is_locked);
    }
}
