<?php

namespace Tests\Feature\Tenancy;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Agreement\Services\TenancyDeboardingService;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Operations\TenantDeboardingResource;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\CreateTenantDeboarding;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\EditTenantDeboarding;
use App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ListTenantDeboardings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        $deboarding = $service->initiateDeboarding($this->agreement, [
            'notice_date' => now()->toDateString(),
            'vacating_date' => now()->addDays(30)->toDateString(),
            'deboarding_reason' => 'Agreement Expiry',
            'deboarding_notes' => 'Tenant moving to another city.',
        ]);

        $this->agreement->refresh();
        $this->assertEquals('deboarding_initiated', $this->agreement->status);
        $this->assertEquals('Agreement Expiry', $this->agreement->deboarding_reason);

        $this->assertInstanceOf(TenantDeboarding::class, $deboarding);
        $this->assertNotNull($deboarding->move_out_audit_id);
        $this->assertEquals($this->agreement->id, $deboarding->tenancy_agreement_id);
        $this->assertEquals($this->property->id, $deboarding->property_id);

        $moveOutAudit = $deboarding->moveOutAudit;
        $this->assertNotNull($moveOutAudit);
        $this->assertEquals(AuditType::MOVE_OUT, $moveOutAudit->audit_type);
        $this->assertEquals($this->moveInAudit->id, $moveOutAudit->reference_audit_id);
    }

    public function test_can_create_linked_maintenance_from_deboarding(): void
    {
        $service = app(TenancyDeboardingService::class);

        $deboarding = $service->initiateDeboarding($this->agreement, [
            'notice_date' => now()->toDateString(),
            'vacating_date' => now()->addDays(15)->toDateString(),
        ]);

        $maintenance = $service->createMaintenanceForDeboarding($deboarding, [
            'title' => 'Living Room Wall Painting & Deep Cleaning',
            'description' => 'Scratches on wall and deep cleaning required.',
            'priority' => MaintenancePriority::HIGH,
            'payer_type' => PayerType::TENANT,
            'estimated_cost' => 5000.00,
            'tenant_amount' => 5000.00,
            'owner_amount' => 0.00,
        ], $this->user);

        $this->assertInstanceOf(MaintenanceRequest::class, $maintenance);
        $this->assertEquals($deboarding->id, $maintenance->tenant_deboarding_id);
        $this->assertEquals(5000.00, $maintenance->tenant_amount);

        $deboarding->refresh();
        $this->assertTrue($deboarding->damages_identified);
        $this->assertEquals(DeboardingStatus::MAINTENANCE_REQUIRED, $deboarding->status);
        $this->assertEquals(5000.00, $deboarding->maintenance_deduction);
        $this->assertEquals(35000.00, $deboarding->net_deposit_refund); // 40000 - 5000 = 35000
    }

    public function test_calculates_security_deposit_settlement_with_deductions(): void
    {
        $service = app(TenancyDeboardingService::class);

        $deboarding = $service->initiateDeboarding($this->agreement, [
            'notice_date' => now()->toDateString(),
            'vacating_date' => now()->addDays(10)->toDateString(),
        ]);

        // Add maintenance
        $service->createMaintenanceForDeboarding($deboarding, [
            'title' => 'Door Lock Repair',
            'estimated_cost' => 3000.00,
            'tenant_amount' => 3000.00,
        ]);

        // Add utility & other deductions directly
        $deboarding->update([
            'utility_deduction' => 2000.00,
            'other_deductions' => 1000.00,
        ]);

        $settlement = $service->calculateSettlement($deboarding);

        $this->assertEquals(40000.00, $settlement['deposit_held']);
        $this->assertEquals(3000.00, $settlement['maintenance_deduction']);
        $this->assertEquals(2000.00, $settlement['utility_deduction']);
        $this->assertEquals(1000.00, $settlement['other_deductions']);
        $this->assertEquals(6000.00, $settlement['total_deductions']);
        $this->assertEquals(34000.00, $settlement['net_refund']); // 40000 - 6000 = 34000
        $this->assertEquals(0.00, $settlement['excess_due']);
    }

    public function test_can_complete_deboarding_and_vacate_property(): void
    {
        $service = app(TenancyDeboardingService::class);

        $deboarding = $service->initiateDeboarding($this->agreement, [
            'notice_date' => now()->toDateString(),
            'vacating_date' => now()->toDateString(),
            'deboarding_reason' => 'Mutual Agreement',
        ]);

        $moveOutAudit = $deboarding->moveOutAudit;
        $moveOutAudit->update(['status' => AuditStatus::APPROVED]);

        $service->completeDeboardingAndVacate(
            $deboarding,
            'vacant',
            [
                'net_refund' => 35000.00,
                'settlement_status' => 'settled',
                'refund_payment_mode' => 'Bank Transfer (NEFT / IMPS / RTGS)',
                'refund_transaction_reference' => 'UTR987654321',
            ],
            $this->user
        );

        $this->agreement->refresh();
        $this->property->refresh();
        $deboarding->refresh();
        $moveOutAudit->refresh();

        $this->assertEquals('vacated', $this->agreement->status);
        $this->assertTrue($this->agreement->keys_returned);
        $this->assertEquals(DeboardingStatus::COMPLETED, $deboarding->status);
        $this->assertTrue($deboarding->keys_returned);
        $this->assertEquals(35000.00, $deboarding->net_deposit_refund);
        $this->assertEquals('vacant', $this->property->status);
        $this->assertTrue($moveOutAudit->is_locked);
    }

    public function test_filament_resource_pages_render_successfully(): void
    {
        $service = app(TenancyDeboardingService::class);
        $deboarding = $service->initiateDeboarding($this->agreement, [
            'notice_date' => now()->toDateString(),
            'vacating_date' => now()->addDays(30)->toDateString(),
        ]);

        Livewire::test(ListTenantDeboardings::class)
            ->assertSuccessful();

        Livewire::test(CreateTenantDeboarding::class)
            ->assertSuccessful();

        Livewire::test(EditTenantDeboarding::class, ['record' => $deboarding->id])
            ->assertSuccessful();

        Livewire::test(\App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingAudit::class, ['record' => $deboarding->id])
            ->assertSuccessful();

        Livewire::test(\App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingMaintenance::class, ['record' => $deboarding->id])
            ->assertSuccessful();

        Livewire::test(\App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingKeys::class, ['record' => $deboarding->id])
            ->assertSuccessful();

        Livewire::test(\App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingSettlement::class, ['record' => $deboarding->id])
            ->assertSuccessful();

        Livewire::test(\App\Filament\Resources\Operations\TenantDeboardingResource\Pages\ManageDeboardingCompletion::class, ['record' => $deboarding->id])
            ->assertSuccessful();

        Livewire::test(\App\Filament\Resources\TenancyAgreements\Pages\DeboardTenancy::class, ['record' => $this->agreement->id])
            ->assertSuccessful();
    }
}
