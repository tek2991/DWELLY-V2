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

        // 5. If quotation is approved, unlocking throws exception
        $newQuote = \App\Domain\Maintenance\Models\MaintenanceClientQuote::create([
            'quote_number' => 'QTE-2026-HILL-2',
            'maintenance_request_id' => $request->id,
            'status' => 'approved',
            'awarded_vendor_quote_ids' => [99],
            'total_amount' => 4500.00,
        ]);
        $request->update(['current_client_quote_id' => $newQuote->id, 'status' => MaintenanceStatus::IN_PROGRESS]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot unlock financial responsibility after Quotation has been approved or Work Orders have been issued.');
        $billingService->archiveQuotationAndUnlock($request);
    }

    public function test_maintenance_request_is_locked_when_quotation_is_approved(): void
    {
        $property = Property::create([
            'building_name' => 'Palm Grove 404',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Master Bathroom Water Heater Replacement',
            'description' => 'Geyser is short circuiting and leaking from the base.',
            'priority' => MaintenancePriority::HIGH,
            'reporter_type' => 'staff',
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
            'status' => MaintenanceStatus::SUBMITTED,
        ]);

        $item = \App\Domain\Maintenance\Models\MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'issue_description' => 'Geyser base leakage and rust damage',
            'repair_action' => 'Replace geyser unit',
            'status' => 'pending',
        ]);

        $user = \App\Models\User::factory()->create();

        // 1. Initial State: Request is NOT locked
        $this->assertFalse($request->isQuotationApproved());
        $this->assertFalse($request->isLocked());

        $testInitial = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class, [
                'record' => $request->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertDontSee('Maintenance Request Locked');

        $initialForm = $testInitial->instance()->getSchema('form');
        $this->assertFalse($initialForm->getComponent('property_id')->isDisabled());
        $this->assertFalse($initialForm->getComponent('title')->isDisabled());
        $this->assertFalse($initialForm->getComponent('priority')->isDisabled());
        $this->assertFalse($initialForm->getComponent('reporter_type')->isDisabled());
        $this->assertFalse($initialForm->getComponent('description')->isDisabled());

        // 2. Create and Approve Client Quotation
        $quote = \App\Domain\Maintenance\Models\MaintenanceClientQuote::create([
            'quote_number' => 'QTE-2026-PALM-1',
            'maintenance_request_id' => $request->id,
            'status' => 'approved',
            'total_amount' => 7500.00,
            'approved_at' => now(),
            'approval_notes' => 'Approved via Email by Owner Mr. David',
        ]);

        $request->update([
            'current_client_quote_id' => $quote->id,
            'quotation_status' => 'approved',
            'quotation_approved_at' => now(),
            'status' => MaintenanceStatus::QUOTATION_APPROVED,
        ]);
        $request->refresh();

        // 3. Verify Model lock helpers
        $this->assertTrue($request->isQuotationApproved());
        $this->assertTrue($request->isLocked());

        // 4. Verify Form inputs are disabled (locked)
        $testLocked = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class, [
                'record' => $request->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('Maintenance Request Locked')
            ->assertSee('Ticket Locked');

        $lockedForm = $testLocked->instance()->getSchema('form');
        $this->assertTrue($lockedForm->getComponent('property_id')->isDisabled());
        $this->assertTrue($lockedForm->getComponent('title')->isDisabled());
        $this->assertTrue($lockedForm->getComponent('priority')->isDisabled());
        $this->assertTrue($lockedForm->getComponent('reporter_type')->isDisabled());
        $this->assertTrue($lockedForm->getComponent('description')->isDisabled());
        $this->assertTrue($lockedForm->getComponent('payer_type')->isDisabled());
        $this->assertTrue($lockedForm->getComponent('is_direct_vendor')->isDisabled());

        // 5. Verify Defect Items Relation Manager has create/edit hidden and view active
        $testItems = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers\ItemsRelationManager::class, [
                'ownerRecord' => $request,
                'pageClass' => \App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class,
            ])
            ->assertSuccessful()
            ->assertTableActionHidden('create')
            ->assertTableActionHidden('edit', $item)
            ->assertTableActionVisible('view', $item);

        // 6. Attempting to unlock financial responsibility throws RuntimeException
        $billingService = app(\App\Domain\Maintenance\Services\MaintenanceBillingService::class);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot unlock financial responsibility after Quotation has been approved or Work Orders have been issued.');
        $billingService->archiveQuotationAndUnlock($request);
    }

    public function test_maintenance_request_pdf_generation_service(): void
    {
        $property = Property::create([
            'building_name' => 'Palm Grove 404',
            'status' => 'active',
        ]);

        $user = \App\Models\User::factory()->create();

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Electrical Panel Repair',
            'description' => 'Circuit breaker tripping repeatedly in kitchen.',
            'status' => MaintenanceStatus::IN_PROGRESS,
            'payer_type' => PayerType::OWNER,
            'owner_amount' => 3500.00,
            'total_cost' => 3500.00,
        ]);

        $item = MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'issue_description' => 'Damaged 32A MCB switch',
            'repair_action' => 'Replaced with new Schneider 32A MCB and tested load',
            'status' => 'completed',
        ]);

        $pdfService = app(\App\Domain\Maintenance\Services\MaintenanceRequestPdfService::class);
        $pdfInstance = $pdfService->buildPdfInstance($request);

        $output = $pdfInstance->output();
        $this->assertNotEmpty($output);
        $this->assertStringStartsWith('%PDF-', $output);

        // Test stream endpoint
        $response = $this->actingAs($user)->get(route('operations.maintenance_requests.pdf', ['record' => $request]));
        $response->assertSuccessful();
        $this->assertEquals('application/pdf', $response->headers->get('Content-Type'));

        // Test modal component view renders iframe and download button
        $modalHtml = view('filament.forms.components.maintenance-report-modal', ['ticket' => $request])->render();
        $this->assertStringContainsString('<iframe', $modalHtml);
        $this->assertStringContainsString(route('operations.maintenance_requests.pdf.download', ['record' => $request]), $modalHtml);
        $this->assertStringContainsString('Download PDF', $modalHtml);
        $this->assertStringNotContainsString('Unable to load maintenance request preview', $modalHtml);
    }

    public function test_mark_work_completed_with_client_acceptance_proof(): void
    {
        $ownerParty = Party::create([
            'party_type' => 'individual',
            'display_name' => 'John Doe',
        ]);
        $ownerParty->enableRole(\App\Domain\Party\Enums\BusinessRole::OWNER);

        $property = Property::create([
            'building_name' => 'Palm Grove 404',
            'status' => 'active',
        ]);

        $user = \App\Models\User::factory()->create();

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'owner_id' => $ownerParty->id,
            'title' => 'Plumbing Leak Fix',
            'status' => MaintenanceStatus::IN_PROGRESS,
            'payer_type' => PayerType::OWNER,
            'total_cost' => 2500,
        ]);

        $item = MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'issue_description' => 'Kitchen sink drain pipe leak',
            'repair_action' => 'Replaced trap and PVC pipe with new seals',
        ]);

        // Attach dummy after-repair photo and acceptance proof so validation passes
        $item->addMedia(\Illuminate\Http\UploadedFile::fake()->image('repaired_photo.jpg'))->toMediaCollection('repaired_photos');
        $request->addMedia(\Illuminate\Http\UploadedFile::fake()->image('client_proof.jpg'))->toMediaCollection('client_acceptance_proofs');

        $this->assertFalse($request->isWorkCompleted());
        $this->assertTrue($request->hasClientAcceptance());

        $testRelation = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers\VerificationAuditRelationManager::class, [
                'ownerRecord' => $request,
                'pageClass' => \App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class,
            ])
            ->assertSuccessful()
            ->callTableAction('recordClientAcceptanceAndComplete', null, [
                'client_accepted_by_name' => 'John Doe (Owner)',
                'client_accepted_at' => now()->toDateTimeString(),
                'client_acceptance_notes' => 'Inspected work in person and signed handover.',
            ])
            ->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertEquals(MaintenanceStatus::WORK_COMPLETED, $request->status);
        $this->assertNotNull($request->completed_at);
        $this->assertEquals('John Doe (Owner)', $request->client_accepted_by_name);
        $this->assertNotNull($request->client_accepted_at);
        $this->assertTrue($request->isWorkCompleted());
        $this->assertTrue($request->hasClientAcceptance());
        // Verify that on-site quality audit is NOT mandatory to complete work
        $this->assertNull($request->triggered_audit_id);

        // Verify that client invoice is NOT autogenerated upon completion
        $this->assertNull($request->owner_invoice_id);

        // Manually generate client invoice via table action
        $testRelation
            ->callTableAction('generateClientInvoice', null, [
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(7)->toDateString(),
                'notes' => 'Test invoice notes',
            ])
            ->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertNotNull($request->owner_invoice_id);
        $this->assertEquals(MaintenanceStatus::INVOICED, $request->status);

        // Verify that ticket can be closed directly without requiring an audit
        $testPage = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class, [
                'record' => $request->getRouteKey(),
            ])
            ->assertSuccessful()
            ->callAction('closeTicket')
            ->assertHasNoActionErrors();

        $request->refresh();
        $this->assertEquals(MaintenanceStatus::CLOSED, $request->status);
    }

    public function test_vendor_bills_are_not_auto_generated_when_work_orders_issued_and_can_be_generated_manually(): void
    {
        $user = \App\Models\User::factory()->create();
        $property = Property::create([
            'building_name' => 'Silver Oak 101',
            'status' => 'active',
        ]);
        $trade = \App\Domain\Party\Models\VendorTrade::create(['name' => 'HVAC']);
        $vendorParty = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Apex Air Conditioning',
        ]);
        $vendorParty->enableRole(\App\Domain\Party\Enums\BusinessRole::VENDOR, ['vendor_trade_id' => $trade->id]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'AC Compressor Failure',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'client_accepted_by_name' => 'Owner Doe',
            'client_accepted_at' => now(),
        ]);

        $quote = \App\Domain\Maintenance\Models\MaintenanceClientQuote::create([
            'maintenance_request_id' => $request->id,
            'quote_number' => 'QT-2026-AC01',
            'status' => 'approved',
            'total_amount' => 8500,
            'subtotal_amount' => 7500,
        ]);

        $vendorQuote = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::create([
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendorParty->id,
            'trade_title' => 'HVAC Compressor Replacement',
            'quoted_cost' => 7500,
            'status' => 'submitted',
        ]);

        $billingService = app(\App\Domain\Maintenance\Services\MaintenanceBillingService::class);
        $billingService->awardVendorQuotesAndIssueWorkOrders($quote, [$vendorQuote->id]);

        $vendorQuote->refresh();
        $this->assertTrue((bool) $vendorQuote->is_awarded);
        $this->assertNotNull($vendorQuote->work_order_number);
        // Bill should NOT be automatically generated on work order issuance
        $this->assertNull($vendorQuote->bill_id);

        // Mark ticket work completed
        $request->update(['status' => MaintenanceStatus::WORK_COMPLETED]);

        // Manually generate vendor bills via table action
        \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers\VerificationAuditRelationManager::class, [
                'ownerRecord' => $request,
                'pageClass' => \App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class,
            ])
            ->assertSuccessful()
            ->callTableAction('generateVendorBills', null, [
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'notes' => 'Test vendor bill notes',
            ])
            ->assertHasNoTableActionErrors();

        $vendorQuote->refresh();
        $this->assertNotNull($vendorQuote->bill_id);

        $bill = \Tek2991\Accounting\Models\Bill::find($vendorQuote->bill_id);
        $this->assertNotNull($bill);
        $this->assertEquals(7500, (float) $bill->grand_total);
        $this->assertEquals(\App\Domain\Maintenance\Models\MaintenanceRequest::class, $bill->reference_type);
        $this->assertEquals($request->id, $bill->reference_id);
    }

    public function test_start_repair_action_locks_repair_decision_and_financial_responsibility_for_direct_route(): void
    {
        $property = Property::create([
            'building_name' => 'Sunset Villa 101',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Direct Route Glass Window Repair',
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => true,
            'status' => MaintenanceStatus::SUBMITTED,
        ]);

        $user = \App\Models\User::factory()->create();

        // 1. Initial State: Form fields are enabled
        $this->assertFalse($request->isLocked());
        $testInitial = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class, [
                'record' => $request->getRouteKey(),
            ])
            ->assertSuccessful();

        $formInitial = $testInitial->instance()->getSchema('form');
        $this->assertFalse($formInitial->getComponent('payer_type')->isDisabled());
        $this->assertFalse($formInitial->getComponent('is_direct_vendor')->isDisabled());

        // 2. Call startRepair action on the Edit page
        $testInitial->callAction('startRepair')
            ->assertHasNoActionErrors();

        $request->refresh();
        $this->assertEquals(MaintenanceStatus::IN_PROGRESS, $request->status);
        $this->assertTrue($request->isLocked());

        // 3. Verify Form is now locked
        $testLocked = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class, [
                'record' => $request->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('Maintenance Request Locked')
            ->assertSee('Repairs are authorized');

        $formLocked = $testLocked->instance()->getSchema('form');
        $this->assertTrue($formLocked->getComponent('payer_type')->isDisabled());
        $this->assertTrue($formLocked->getComponent('is_direct_vendor')->isDisabled());
        $this->assertTrue($formLocked->getComponent('property_id')->isDisabled());
        $this->assertTrue($formLocked->getComponent('title')->isDisabled());
    }

    public function test_direct_repair_route_does_not_generate_client_invoices_or_vendor_bills(): void
    {
        $owner = \App\Domain\Party\Models\Party::create([
            'display_name' => 'Direct Owner Alice',
            'party_type' => 'individual',
        ]);

        $property = Property::create([
            'building_name' => 'Meadow View 404',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'title' => 'Direct Route Paint Touchup',
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => true,
            'status' => MaintenanceStatus::IN_PROGRESS,
            'client_accepted_at' => now(),
            'client_accepted_by_name' => 'Alice',
        ]);

        $user = \App\Models\User::factory()->create();

        // 1. In VerificationAuditRelationManager, invoice and bill header actions must be hidden
        $testManager = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers\VerificationAuditRelationManager::class, [
                'ownerRecord' => $request,
                'pageClass' => \App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class,
            ])
            ->assertSuccessful()
            ->assertTableActionHidden('generateClientInvoice')
            ->assertTableActionHidden('generateVendorBills')
            ->assertSee('Work completed &amp; accepted by Alice', false);

        // 2. In EditMaintenanceRequest sidebar, client acceptance summary card is rendered
        $testEdit = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class, [
                'record' => $request->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('Direct Repair Settlement')
            ->assertSee('Acceptance Confirmed');

        // 3. Calling createMaintenanceInvoice on direct request throws InvalidArgumentException
        $billingService = app(\App\Domain\Maintenance\Services\MaintenanceBillingService::class);
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot generate client invoice for direct repair tickets');
        $billingService->createMaintenanceInvoice($request, 'owner_invoice');
    }

    public function test_direct_repair_route_acceptance_proof_is_optional(): void
    {
        $owner = \App\Domain\Party\Models\Party::create([
            'display_name' => 'Owner Bob',
            'party_type' => 'individual',
        ]);

        $property = Property::create([
            'building_name' => 'Oakwood Residency 102',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'owner_id' => $owner->id,
            'title' => 'Direct Route Lock Repair',
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => true,
            'status' => MaintenanceStatus::IN_PROGRESS,
        ]);

        $item = $request->items()->create([
            'issue_description' => 'Broken front door handle',
            'repair_action' => 'Replaced lock latch and lubricated hinges',
            'status' => 'completed',
        ]);
        $item->addMedia(\Illuminate\Http\UploadedFile::fake()->image('after.jpg'))->toMediaCollection('repaired_photos');

        $user = \App\Models\User::factory()->create();

        // Calling recordClientAcceptanceAndComplete without uploading proofs should succeed for direct repairs
        \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\RelationManagers\VerificationAuditRelationManager::class, [
                'ownerRecord' => $request,
                'pageClass' => \App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class,
            ])
            ->assertSuccessful()
            ->callTableAction('recordClientAcceptanceAndComplete', null, [
                'client_accepted_by_name' => 'Bob Owner',
                'client_accepted_at' => now()->toDateString(),
                'client_acceptance_notes' => 'Self-repaired and confirmed satisfactory.',
            ])
            ->assertHasNoTableActionErrors();

        $request->refresh();
        $this->assertEquals(MaintenanceStatus::WORK_COMPLETED, $request->status);
        $this->assertEquals('Bob Owner', $request->client_accepted_by_name);
        $this->assertCount(0, $request->getMedia('client_acceptance_proofs'));
    }

    public function test_close_ticket_action_is_disabled_until_work_completed_and_then_enabled_and_highlighted(): void
    {
        $user = \App\Models\User::factory()->create();

        $property = Property::create([
            'building_name' => 'Highland Tower 301',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Electrical Short Circuit',
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => true,
            'status' => MaintenanceStatus::IN_PROGRESS,
        ]);

        // 1. When status is IN_PROGRESS (work not completed), closeTicket action on Edit page is disabled and gray
        $testPage = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class, [
                'record' => $request->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertActionDisabled('closeTicket');

        $closeAction = $testPage->instance()->getAction('closeTicket');
        $this->assertEquals('gray', $closeAction->getColor());

        // 2. Mark work completed
        $request->update([
            'status' => MaintenanceStatus::WORK_COMPLETED,
            'completed_at' => now(),
        ]);

        // 3. When status is WORK_COMPLETED, closeTicket action on Edit page is enabled and highlighted (success)
        $testPageCompleted = \Livewire\Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest::class, [
                'record' => $request->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertActionEnabled('closeTicket');

        $closeActionCompleted = $testPageCompleted->instance()->getAction('closeTicket');
        $this->assertEquals('success', $closeActionCompleted->getColor());

        // 4. Closing ticket succeeds
        $testPageCompleted->callAction('closeTicket')
            ->assertHasNoActionErrors();

        $request->refresh();
        $this->assertEquals(MaintenanceStatus::CLOSED, $request->status);
    }
}




