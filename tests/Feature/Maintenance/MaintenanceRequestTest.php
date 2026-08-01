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
}
