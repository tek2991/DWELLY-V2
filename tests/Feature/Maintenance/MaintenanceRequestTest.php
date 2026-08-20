<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditCategory;
use App\Domain\Audit\Models\AuditItem;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceRequestItem;
use App\Domain\Maintenance\Services\MaintenanceAuditTriggerService;
use App\Domain\Maintenance\Services\MaintenanceSettlementService;
use App\Domain\Party\Enums\VendorOnboardingStatus;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\VendorProfile;
use App\Domain\Party\Models\VendorTrade;
use App\Domain\Party\Services\VendorOnboardingService;
use App\Domain\Property\Models\InventoryType;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyInventory;
use App\Domain\Property\Models\PropertyRoom;
use App\Domain\Property\Models\PropertyUtility;
use App\Domain\Property\Models\RoomDefinition;
use App\Domain\Property\Models\RoomType;
use App\Domain\Property\Models\UtilityType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_onboard_and_verify_vendor(): void
    {
        $party = Party::create([
            'party_type' => 'organization',
            'display_name' => 'Apex Plumbing & Maintenance Ltd',
            'email' => 'apex@plumbing.com',
        ]);

        $trade = VendorTrade::create([
            'name' => 'Plumbing',
            'slug' => 'plumbing',
            'is_active' => true,
        ]);

        $profile = VendorProfile::create([
            'party_id' => $party->id,
            'vendor_trade_id' => $trade->id,
            'onboarding_status' => VendorOnboardingStatus::DRAFT,
        ]);

        $service = app(VendorOnboardingService::class);
        $service->submitForVerification($profile);

        $this->assertEquals(VendorOnboardingStatus::PENDING_VERIFICATION, $profile->fresh()->onboarding_status);

        $service->verifyVendor($profile, null, 'Verified licenses & tax details');

        $this->assertEquals(VendorOnboardingStatus::VERIFIED, $profile->fresh()->onboarding_status);
        $this->assertTrue($profile->isVerified());
    }

    public function test_can_create_party_with_vendor_data_via_party_service(): void
    {
        $trade = VendorTrade::create([
            'name' => 'Carpentry',
            'slug' => 'carpentry',
            'is_active' => true,
        ]);

        $partyService = app(\App\Domain\Party\Services\PartyService::class);
        $party = $partyService->createParty([
            'party_type' => 'organization',
            'display_name' => 'Elite Woodworks',
            'email' => 'elite@woodworks.test',
            'organization_data' => [
                'legal_name' => 'Elite Woodworks Pvt Ltd',
            ],
            'vendor_data' => [
                'vendor_trade_id' => $trade->id,
                'onboarding_status' => VendorOnboardingStatus::VERIFIED->value,
                'is_preferred' => true,
                'verification_notes' => 'Trade license verified',
            ],
        ], ['vendor']);

        $this->assertNotNull($party->id);
        $this->assertEquals('Elite Woodworks', $party->display_name);
        $this->assertNotNull($party->vendorProfile);
        $this->assertEquals($trade->id, $party->vendorProfile->vendor_trade_id);
        $this->assertTrue((bool)$party->vendorProfile->is_preferred);
        $this->assertEquals('Trade license verified', $party->vendorProfile->verification_notes);
    }

    public function test_can_quick_create_individual_artisan_vendor(): void
    {
        $trade = VendorTrade::create([
            'name' => 'Plumbing',
            'slug' => 'plumbing-test',
            'is_active' => true,
        ]);

        $partyService = app(\App\Domain\Party\Services\PartyService::class);
        $party = $partyService->createParty([
            'party_type' => 'individual',
            'display_name' => 'Ramesh Kumar (Plumber)',
            'phone' => '+919876543210',
            'individual_data' => [
                'name' => 'Ramesh Kumar (Plumber)',
            ],
            'vendor_data' => [
                'vendor_trade_id' => $trade->id,
                'onboarding_status' => VendorOnboardingStatus::VERIFIED->value,
                'is_preferred' => true,
                'verification_notes' => 'Quick-created from Maintenance Quotation',
            ],
        ], ['vendor']);

        $this->assertNotNull($party->id);
        $this->assertEquals('Ramesh Kumar (Plumber)', $party->display_name);
        $this->assertEquals('individual', $party->party_type);
        $this->assertNotNull($party->vendorProfile);
        $this->assertEquals($trade->id, $party->vendorProfile->vendor_trade_id);
        $this->assertTrue($party->vendorProfile->isVerified());
        $this->assertNotNull($party->accounting_contact_id);
    }

    public function test_can_create_maintenance_request_with_items(): void
    {
        $property = Property::create([
            'building_name' => 'Skyline Heights Apt 402',
            'status' => 'active',
        ]);

        $roomType = RoomType::create(['name' => 'Living Space', 'slug' => 'living-space']);
        $roomDef = RoomDefinition::create(['room_type_id' => $roomType->id, 'name' => 'Living Room', 'slug' => 'living-room']);
        $room = PropertyRoom::create([
            'property_id' => $property->id,
            'room_definition_id' => $roomDef->id,
            'custom_name' => 'Main Living Room',
        ]);

        $invType = InventoryType::create(['name' => 'Air Conditioner', 'slug' => 'air-conditioner']);
        $inventory = PropertyInventory::create([
            'property_id' => $property->id,
            'inventory_type_id' => $invType->id,
            'count' => 2,
        ]);

        $utilType = UtilityType::create(['name' => 'Water', 'slug' => 'water']);
        $utility = PropertyUtility::create([
            'property_id' => $property->id,
            'utility_type_id' => $utilType->id,
            'paid_by' => 'owner',
            'effective_from' => now(),
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'AC Leakage & Water Meter Leak',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::SUBMITTED,
        ]);

        MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'itemable_type' => PropertyRoom::class,
            'itemable_id' => $room->id,
            'issue_description' => 'Wall dampness near living room AC',
        ]);

        MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'itemable_type' => PropertyInventory::class,
            'itemable_id' => $inventory->id,
            'issue_description' => 'AC water leaking inside room',
        ]);

        MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'itemable_type' => PropertyUtility::class,
            'itemable_id' => $utility->id,
            'issue_description' => 'Water meter valve stuck',
        ]);

        $this->assertCount(3, $request->items);
        $this->assertStringStartsWith('MNT-', $request->ticket_number);
    }

    public function test_direct_payment_settlement(): void
    {
        $property = Property::create([
            'building_name' => 'Greenwood Villas',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Direct Owner Door Repair',
            'status' => 'in_progress',
        ]);

        $settlementService = app(MaintenanceSettlementService::class);
        $settlementService->settleMaintenanceRequest(
            request: $request,
            payerType: PayerType::OWNER_DIRECT,
            totalCost: 1500.00,
            directPaymentRef: 'UPI-REC-998811',
            directPaymentNotes: 'Owner paid carpenter directly via GPay'
        );

        $request->refresh();
        $this->assertEquals(PayerType::OWNER_DIRECT, $request->payer_type);
        $this->assertFalse($request->is_dwelly_involved);
        $this->assertEquals(1500.00, $request->total_cost);
        $this->assertEquals(1500.00, $request->owner_amount);
        $this->assertEquals(0.00, $request->dwelly_amount);
        $this->assertEquals('UPI-REC-998811', $request->direct_payment_reference);
        $this->assertEquals(MaintenanceStatus::WORK_COMPLETED, $request->status);
    }

    public function test_dwelly_split_payment_settlement(): void
    {
        $property = Property::create([
            'building_name' => 'Harmony Heights 101',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Major Plumbing Overhaul',
            'status' => 'in_progress',
        ]);

        $settlementService = app(MaintenanceSettlementService::class);
        $settlementService->settleMaintenanceRequest(
            request: $request,
            payerType: PayerType::DWELLY_INVOICE_SPLIT,
            totalCost: 10000.00,
            ownerAmount: 6000.00,
            tenantAmount: 2000.00,
            dwellyAmount: 2000.00
        );

        $request->refresh();
        $this->assertEquals(PayerType::DWELLY_INVOICE_SPLIT, $request->payer_type);
        $this->assertTrue($request->is_dwelly_involved);
        $this->assertEquals(10000.00, $request->total_cost);
        $this->assertEquals(6000.00, $request->owner_amount);
        $this->assertEquals(2000.00, $request->tenant_amount);
        $this->assertEquals(2000.00, $request->dwelly_amount);
    }

    public function test_trigger_post_repair_audit(): void
    {
        $property = Property::create([
            'building_name' => 'Royal Park #12',
            'status' => 'active',
        ]);

        $roomType = RoomType::create(['name' => 'Bedroom', 'slug' => 'bedroom']);
        $roomDef = RoomDefinition::create(['room_type_id' => $roomType->id, 'name' => 'Master Bedroom', 'slug' => 'master-bedroom']);
        $room = PropertyRoom::create([
            'property_id' => $property->id,
            'room_definition_id' => $roomDef->id,
        ]);

        $invType = InventoryType::create(['name' => 'Geyser', 'slug' => 'geyser']);
        $inventory = PropertyInventory::create([
            'property_id' => $property->id,
            'inventory_type_id' => $invType->id,
        ]);

        $inspectorUser = \App\Models\User::factory()->create();

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Geyser Replacement',
            'status' => MaintenanceStatus::WORK_COMPLETED,
            'assigned_inspector_id' => $inspectorUser->id,
        ]);

        MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'itemable_type' => PropertyRoom::class,
            'itemable_id' => $room->id,
            'issue_description' => 'Geyser wiring inspection in Master Bedroom',
            'repair_action' => 'Rewired',
        ]);

        MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'itemable_type' => PropertyInventory::class,
            'itemable_id' => $inventory->id,
            'issue_description' => 'Old geyser burnt out',
            'repair_action' => 'Replaced with new 25L Geyser',
        ]);

        $auditService = app(MaintenanceAuditTriggerService::class);
        $audit = $auditService->triggerAudit($request);

        $request->refresh();
        $this->assertNotNull($request->triggered_audit_id);
        $this->assertEquals($audit->id, $request->triggered_audit_id);
        $this->assertEquals(AuditType::MAINTENANCE, $audit->audit_type);
        $this->assertEquals(AuditStatus::DRAFT, $audit->status);
        $this->assertEquals($inspectorUser->id, $audit->inspector_id);

        $roomsCategory = AuditCategory::where('audit_id', $audit->id)->where('name', 'Rooms')->first();
        $this->assertNotNull($roomsCategory);
        $roomAuditItem = $roomsCategory->items->firstWhere('source_type', PropertyRoom::class);
        $this->assertNotNull($roomAuditItem);
        $this->assertStringContainsString('Master Bedroom', $roomAuditItem->name);

        $invCategory = AuditCategory::where('audit_id', $audit->id)->where('name', 'Inventory')->first();
        $this->assertNotNull($invCategory);
        $invAuditItem = $invCategory->items->firstWhere('source_type', PropertyInventory::class);
        $this->assertNotNull($invAuditItem);
        $this->assertStringContainsString('Geyser', $invAuditItem->name);
    }

    public function test_direct_vendor_workflow_path(): void
    {
        $property = Property::create([
            'building_name' => 'Sunset Towers 304',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Direct Owner Door Repair',
            'payer_type' => PayerType::OWNER_DIRECT,
            'is_direct_vendor' => true,
            'status' => MaintenanceStatus::SUBMITTED,
        ]);

        $this->assertTrue($request->is_direct_vendor);
        $this->assertEquals(MaintenanceStatus::SUBMITTED, $request->status);

        // Mark repair completed
        $request->update(['status' => MaintenanceStatus::WORK_COMPLETED, 'completed_at' => now()]);
        $this->assertEquals(MaintenanceStatus::WORK_COMPLETED, $request->status);
    }

    public function test_dwelly_facilitated_workflow_path_with_quotation_approval_proof(): void
    {
        $property = Property::create([
            'building_name' => 'Palm Grove 501',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Dwelly Facilitated Water Heater Replacement',
            'payer_type' => PayerType::DWELLY_INVOICE_OWNER,
            'is_direct_vendor' => false,
            'status' => MaintenanceStatus::SUBMITTED,
        ]);

        $this->assertFalse($request->is_direct_vendor);

        // 1. Upload Quotation
        $request->update([
            'quotation_amount' => 4500.00,
            'quotation_notes' => 'Quote for standard 15L geyser replacement',
            'status' => MaintenanceStatus::QUOTATION_PENDING,
        ]);
        $this->assertEquals(MaintenanceStatus::QUOTATION_PENDING, $request->status);

        // 2. Approve Quotation with proof notes
        $request->update([
            'quotation_status' => 'approved',
            'quotation_approved_at' => now(),
            'quotation_approval_notes' => 'Approved by owner via email screenshot',
            'status' => MaintenanceStatus::QUOTATION_APPROVED,
        ]);
        $this->assertEquals('approved', $request->quotation_status);
        $this->assertEquals(MaintenanceStatus::QUOTATION_APPROVED, $request->status);

        // 3. Start Repair & Complete
        $request->update(['status' => MaintenanceStatus::IN_PROGRESS]);
        $this->assertEquals(MaintenanceStatus::IN_PROGRESS, $request->status);

        $request->update(['status' => MaintenanceStatus::WORK_COMPLETED, 'completed_at' => now()]);
        $this->assertEquals(MaintenanceStatus::WORK_COMPLETED, $request->status);
    }

    public function test_repair_decision_is_locked_when_quotation_exists(): void
    {
        $property = Property::create([
            'building_name' => 'Hillside Heights 202',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Kitchen Sink Pipe Replacement',
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
            'status' => MaintenanceStatus::SUBMITTED,
        ]);

        $user = \App\Models\User::factory()->create();

        // 1. Without quotation: payer_type and is_direct_vendor are enabled
        $testWithoutQuote = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class, [
                'record' => $request->getRouteKey(),
            ])
            ->assertSuccessful();

        $formWithoutQuote = $testWithoutQuote->instance()->getSchema('form');
        $payerField = $formWithoutQuote->getComponent('payer_type');
        $this->assertFalse($payerField->isDisabled());

        // 2. Create quotation for this request
        $quote = \App\Domain\Maintenance\Models\MaintenanceClientQuote::create([
            'quote_number' => 'QTE-2026-HILL-1',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
            'total_amount' => 3000.00,
        ]);
        $request->update(['current_client_quote_id' => $quote->id]);
        $request->refresh();

        // 3. With quotation: payer_type and is_direct_vendor are locked (disabled)
        $testWithQuote = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class, [
                'record' => $request->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('Financial Responsibility Locked');

        $formWithQuote = $testWithQuote->instance()->getSchema('form');
        $payerFieldLocked = $formWithQuote->getComponent('payer_type');
        $this->assertTrue($payerFieldLocked->isDisabled());
        $routeFieldLocked = $formWithQuote->getComponent('is_direct_vendor');
        $this->assertTrue($routeFieldLocked->isDisabled());

        // 4. Test unlocking & archiving quotation via billing service
        $billingService = app(\App\Domain\Maintenance\Services\MaintenanceBillingService::class);
        $billingService->archiveQuotationAndUnlock($request);

        $request->refresh();
        $quote->refresh();
        $this->assertNull($request->current_client_quote_id);
        $this->assertEquals('archived', $quote->status);

        // Form should be unlocked again
        $testUnlocked = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class, [
                'record' => $request->getRouteKey(),
            ])
            ->assertSuccessful();

        $formUnlocked = $testUnlocked->instance()->getSchema('form');
        $this->assertFalse($formUnlocked->getComponent('payer_type')->isDisabled());
        $this->assertFalse($formUnlocked->getComponent('is_direct_vendor')->isDisabled());

        // 5. If work orders are issued, unlocking throws exception
        $newQuote = \App\Domain\Maintenance\Models\MaintenanceClientQuote::create([
            'quote_number' => 'QTE-2026-HILL-2',
            'maintenance_request_id' => $request->id,
            'status' => 'approved',
            'awarded_vendor_quote_ids' => [99],
            'total_amount' => 4500.00,
        ]);
        $request->update(['current_client_quote_id' => $newQuote->id, 'status' => MaintenanceStatus::IN_PROGRESS]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot unlock financial responsibility after Work Orders have been issued.');
        $billingService->archiveQuotationAndUnlock($request);
    }
}
