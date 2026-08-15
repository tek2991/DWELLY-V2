<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceRequestItem;
use App\Domain\Maintenance\Models\MaintenanceVendorQuote;
use App\Domain\Maintenance\Services\MaintenanceAuditTriggerService;
use App\Domain\Maintenance\Services\MaintenanceBillingService;
use App\Domain\Maintenance\Services\MaintenanceQuotationService;
use App\Domain\Party\Enums\VendorOnboardingStatus;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\VendorProfile;
use App\Domain\Party\Models\VendorTrade;
use App\Domain\Property\Models\InventoryType;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyInventory;
use App\Domain\Property\Models\PropertyRoom;
use App\Domain\Property\Models\RoomDefinition;
use App\Domain\Property\Models\RoomType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\DocumentSequence;
use Tests\TestCase;

class MaintenanceOverhaulWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Property $property;
    protected Party $ownerParty;
    protected Party $tenantParty;
    protected User $inspector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::create([
            'building_name' => 'Dwelly Sapphire 101',
            'status' => 'active',
        ]);

        $this->ownerParty = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Vikram Singhania (Owner)',
            'email' => 'vikram@dwelly.test',
            'phone' => '9876543210',
        ]);

        $this->tenantParty = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Aarav Patel (Tenant)',
            'email' => 'aarav@dwelly.test',
            'phone' => '9876543211',
        ]);

        $this->inspector = User::factory()->create(['name' => 'Dwelly Field Inspector']);

        // Setup chart of accounts for tests
        Account::create([
            'code' => '4000',
            'name' => 'Maintenance Income',
            'type' => \Tek2991\Accounting\Enums\AccountType::Revenue,
            'status' => 'active',
            'is_system' => true,
        ]);

        Account::create([
            'code' => '5000',
            'name' => 'Maintenance Expense',
            'type' => \Tek2991\Accounting\Enums\AccountType::Expense,
            'status' => 'active',
            'is_system' => true,
        ]);

        DocumentSequence::create([
            'document_type' => 'invoice',
            'prefix' => 'INV-',
            'next_number' => 1,
        ]);

        DocumentSequence::create([
            'document_type' => 'bill',
            'prefix' => 'BILL-',
            'next_number' => 1,
        ]);
    }

    public function test_direct_repair_flow_bypasses_dwelly_financials_and_completes_via_audit(): void
    {
        $roomType = RoomType::create(['name' => 'Master Bed', 'slug' => 'master-bed']);
        $roomDef = RoomDefinition::create(['room_type_id' => $roomType->id, 'name' => 'Master Bedroom', 'slug' => 'master-bedroom']);
        $room = PropertyRoom::create([
            'property_id' => $this->property->id,
            'room_definition_id' => $roomDef->id,
        ]);

        // 1. Issue reported & Direct repair chosen
        $request = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'owner_id' => $this->ownerParty->id,
            'tenant_id' => $this->tenantParty->id,
            'assigned_inspector_id' => $this->inspector->id,
            'title' => 'Door Handle Broken',
            'description' => 'Bedroom door lock mechanism is jammed',
            'priority' => MaintenancePriority::MEDIUM,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::TENANT,
            'is_direct_vendor' => true, // Direct execution
        ]);

        $item = MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'itemable_type' => PropertyRoom::class,
            'itemable_id' => $room->id,
            'issue_description' => 'Lock jammed',
            'repair_action' => 'Replace Lock Mechanism',
            'actual_cost' => 800.00,
        ]);

        $this->assertTrue($request->isDirectRepair());
        $this->assertFalse($request->isDwellyCoordinated());

        // 2. Repairs in progress & completed by tenant's direct vendor
        $request->update([
            'status' => MaintenanceStatus::WORK_COMPLETED,
            'completed_at' => now(),
            'direct_payment_reference' => 'UPI-TANT-9911',
            'direct_payment_notes' => 'Tenant purchased Godrej lock directly and paid carpenter ₹800',
        ]);

        $this->assertEquals(MaintenanceStatus::WORK_COMPLETED, $request->status);
        $this->assertEquals('UPI-TANT-9911', $request->direct_payment_reference);

        // 3. Post-repair verification audit triggered
        $auditService = app(MaintenanceAuditTriggerService::class);
        $audit = $auditService->triggerAudit($request);

        $request->refresh();
        $this->assertNotNull($request->triggered_audit_id);
        $this->assertEquals(MaintenanceStatus::AUDIT_PENDING, $request->status);
        $this->assertEquals(AuditType::MAINTENANCE, $audit->audit_type);
        $this->assertEquals(AuditStatus::DRAFT, $audit->status);

        // 4. Verification audit approved & ticket closed
        $audit->update(['status' => AuditStatus::APPROVED]);

        $request->update([
            'status' => MaintenanceStatus::CLOSED,
            'resolved_at' => now(),
        ]);

        $this->assertEquals(MaintenanceStatus::CLOSED, $request->fresh()->status);
        // Note: No Dwelly bills/invoices were generated for direct execution
        $this->assertNull($request->bill_id);
        $this->assertNull($request->tenant_invoice_id);
    }

    public function test_dwelly_coordinated_multi_vendor_trades_and_client_quotation_preparation(): void
    {
        // 1. Setup multiple vendor trades (Masonry & Painting)
        $masonryTrade = VendorTrade::create(['name' => 'Masonry & Civil', 'slug' => 'masonry', 'is_active' => true]);
        $paintTrade = VendorTrade::create(['name' => 'Painting', 'slug' => 'painting', 'is_active' => true]);

        $masonryVendorParty = Party::create(['display_name' => 'Koren Masonry Works', 'party_type' => 'organization']);
        VendorProfile::create(['party_id' => $masonryVendorParty->id, 'vendor_trade_id' => $masonryTrade->id, 'onboarding_status' => VendorOnboardingStatus::VERIFIED]);

        $paintVendorParty = Party::create(['display_name' => 'Apex Color Paints', 'party_type' => 'organization']);
        VendorProfile::create(['party_id' => $paintVendorParty->id, 'vendor_trade_id' => $paintTrade->id, 'onboarding_status' => VendorOnboardingStatus::VERIFIED]);

        // 2. Create Dwelly-coordinated maintenance request
        $request = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'owner_id' => $this->ownerParty->id,
            'tenant_id' => $this->tenantParty->id,
            'assigned_inspector_id' => $this->inspector->id,
            'title' => 'Living Room Wall Crack & Repaint',
            'description' => 'Plaster cracked due to seepage. Needs plaster patching and waterproof painting.',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false, // Dwelly Coordinated
        ]);

        $quotationService = app(MaintenanceQuotationService::class);

        // 3. Collect Quote 1: Masonry Vendor
        $masonryQuote = $quotationService->addVendorQuote($request, [
            'vendor_party_id' => $masonryVendorParty->id,
            'vendor_trade_id' => $masonryTrade->id,
            'trade_title' => 'Wall Chipping & Cement Plastering',
            'scope_of_work' => 'Chip damaged plaster, apply waterproof bond coating and re-plaster',
            'quoted_cost' => 3500.00,
        ]);

        // 4. Collect Quote 2: Painting Vendor
        $paintQuote = $quotationService->addVendorQuote($request, [
            'vendor_party_id' => $paintVendorParty->id,
            'vendor_trade_id' => $paintTrade->id,
            'trade_title' => 'Primer & 2-Coat Asian Paints Royale',
            'scope_of_work' => 'Sand surface, 1 coat primer, 2 coats washable emulsion paint',
            'quoted_cost' => 4500.00,
        ]);

        $request->refresh();
        $this->assertCount(2, $request->vendorQuotes);
        $this->assertEquals(8000.00, $request->total_vendor_cost);
        $this->assertEquals(MaintenanceStatus::VENDOR_ASSIGNED, $request->status);

        // 5. Dwelly prepares formal Client Quotation (with markup/supervision fee)
        $clientQuote = $quotationService->createOrUpdateClientQuote($request, [
            [
                'vendor_quote_id' => $masonryQuote->id,
                'description' => 'Civil Wall Plastering & Waterproof Treatment',
                'quantity' => 1,
                'unit_price' => 4000.00,
                'total_price' => 4000.00,
            ],
            [
                'vendor_quote_id' => $paintQuote->id,
                'description' => 'Wall Painting (Asian Paints Royale Premium)',
                'quantity' => 1,
                'unit_price' => 5200.00,
                'total_price' => 5200.00,
            ],
            [
                'vendor_quote_id' => null,
                'description' => 'Dwelly Project Supervision & Clean-up Fee',
                'quantity' => 1,
                'unit_price' => 800.00,
                'total_price' => 800.00,
            ],
        ]);

        $request->refresh();
        $this->assertEquals(10000.00, $clientQuote->total_amount);
        $this->assertEquals(10000.00, $clientQuote->owner_amount);
        $this->assertEquals('pending_approval', $clientQuote->status);
        $this->assertEquals(MaintenanceStatus::QUOTATION_PENDING, $request->status);
        $this->assertEquals(10000.00, $request->total_client_cost);
    }

    public function test_client_quotation_approval_with_mandatory_proof_and_totals_sync(): void
    {
        $request = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'owner_id' => $this->ownerParty->id,
            'assigned_inspector_id' => $this->inspector->id,
            'title' => 'Geyser Replacement',
            'priority' => MaintenancePriority::MEDIUM,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $quotationService = app(MaintenanceQuotationService::class);
        $clientQuote = $quotationService->createOrUpdateClientQuote($request, [
            [
                'description' => '25L Bajaj Storage Water Heater + Installation',
                'quantity' => 1,
                'unit_price' => 6500.00,
                'total_price' => 6500.00,
            ]
        ]);

        $this->assertEquals(MaintenanceStatus::QUOTATION_PENDING, $request->fresh()->status);

        // Approve quotation with proof notes
        $quotationService->approveClientQuote($clientQuote, 'Owner approved estimate via WhatsApp confirmation on 15 Aug');

        $request->refresh();
        $this->assertEquals(MaintenanceStatus::QUOTATION_APPROVED, $request->status);
        $this->assertEquals('approved', $request->quotation_status);
        $this->assertNotNull($request->quotation_approved_at);
        $this->assertEquals('approved', $clientQuote->fresh()->status);
        $this->assertEquals(6500.00, $request->total_cost);
    }

    public function test_client_quotation_rejection_reverts_ticket_to_direct_repair_mode(): void
    {
        $request = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'owner_id' => $this->ownerParty->id,
            'assigned_inspector_id' => $this->inspector->id,
            'title' => 'AC Servicing & Gas Refill',
            'priority' => MaintenancePriority::MEDIUM,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $quotationService = app(MaintenanceQuotationService::class);
        $clientQuote = $quotationService->createOrUpdateClientQuote($request, [
            [
                'description' => 'AC Gas Charging & Compressor Cleaning',
                'quantity' => 1,
                'unit_price' => 3800.00,
                'total_price' => 3800.00,
            ]
        ]);

        // Client rejects Dwelly quote because they want to use their warranty technician directly
        $quotationService->rejectClientQuote(
            $clientQuote,
            'Owner stated AC is under manufacturer warranty and brand service technician will visit directly.',
            'revert_to_direct'
        );

        $request->refresh();
        $this->assertTrue($request->is_direct_vendor);
        $this->assertTrue($request->isDirectRepair());
        $this->assertFalse($request->is_dwelly_involved);
        $this->assertEquals('rejected', $request->quotation_status);
        $this->assertEquals(MaintenanceStatus::IN_PROGRESS, $request->status);
        $this->assertStringContainsString('Reverted to Direct Repair', $request->direct_payment_notes);
    }

    public function test_accounting_settlement_creates_vendor_bills_and_client_invoices(): void
    {
        $plumberParty = Party::create(['display_name' => 'QuickFix Plumbing Ltd', 'party_type' => 'organization', 'email' => 'quickfix@plumbing.test']);
        $electricianParty = Party::create(['display_name' => 'Spark Electricals', 'party_type' => 'organization', 'email' => 'spark@electricals.test']);

        $request = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'owner_id' => $this->ownerParty->id,
            'tenant_id' => $this->tenantParty->id,
            'assigned_inspector_id' => $this->inspector->id,
            'title' => 'Bathroom Remodel & Geyser Wiring',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::WORK_COMPLETED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
            'owner_amount' => 12000.00,
            'total_cost' => 12000.00,
        ]);

        $vendorQuote1 = MaintenanceVendorQuote::create([
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $plumberParty->id,
            'trade_title' => 'Plumbing Fitting Replacement',
            'quoted_cost' => 5000.00,
            'status' => 'work_completed',
        ]);

        $vendorQuote2 = MaintenanceVendorQuote::create([
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $electricianParty->id,
            'trade_title' => 'High Load Wiring Installation',
            'quoted_cost' => 4500.00,
            'status' => 'work_completed',
        ]);

        $request->syncQuotationTotals();

        $billingService = app(MaintenanceBillingService::class);

        // 1. Generate Bills for both vendors
        $bills = $billingService->createAllVendorBillsForRequest($request);

        $this->assertCount(2, $bills);
        $this->assertNotNull($vendorQuote1->fresh()->bill_id);
        $this->assertNotNull($vendorQuote2->fresh()->bill_id);
        $this->assertEquals('billed', $vendorQuote1->fresh()->status);
        $this->assertEquals('billed', $vendorQuote2->fresh()->status);

        // 2. Generate Client Invoice to Owner
        $invoice = $billingService->createMaintenanceInvoice($request, 'owner_invoice', [
            [
                'description' => 'Complete Bathroom Renovation & Electrical Overhaul',
                'quantity' => 1,
                'unit_price' => 12000.00,
                'total' => 12000.00,
            ]
        ]);

        $request->refresh();
        $this->assertNotNull($request->owner_invoice_id);
        $this->assertEquals((string) $invoice->id, $request->owner_invoice_id);
    }

    public function test_maintenance_quotation_resource_workflow_integration(): void
    {
        $request = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'owner_party_id' => $this->ownerParty->id,
            'title' => 'Structural Balcony Sealing',
            'description' => 'Balcony waterproofing and painting',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
            'assigned_inspector_id' => $this->inspector->id,
        ]);

        $clientQuote = MaintenanceClientQuote::create([
            'maintenance_request_id' => $request->id,
            'total_amount' => 15000.00,
            'owner_amount' => 15000.00,
            'tenant_amount' => 0.00,
            'dwelly_amount' => 0.00,
            'status' => 'pending_approval',
        ]);

        $request->update([
            'current_client_quote_id' => $clientQuote->id,
            'status' => MaintenanceStatus::QUOTATION_PENDING,
        ]);
        $request->syncQuotationTotals();

        $this->assertEquals(15000.00, (float) $request->fresh()->total_client_cost);
        $this->assertEquals($clientQuote->id, $request->fresh()->current_client_quote_id);

        // Approve quote
        $clientQuote->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approval_notes' => 'Approved via WhatsApp by Owner',
        ]);
        $request->update([
            'quotation_status' => 'approved',
            'quotation_approved_at' => now(),
            'status' => MaintenanceStatus::QUOTATION_APPROVED,
        ]);

        $this->assertTrue($clientQuote->fresh()->isApproved());
        $this->assertEquals(MaintenanceStatus::QUOTATION_APPROVED, $request->fresh()->status);
    }

    public function test_vendor_quote_records_and_work_order_issuance_lifecycle(): void
    {
        $vendor = Party::create([
            'party_type' => 'organization',
            'display_name' => 'Apex Pro Waterproofing',
            'email' => 'apex@vendors.test',
            'phone' => '9876500001',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'owner_party_id' => $this->ownerParty->id,
            'title' => 'Terrace Leakage',
            'description' => 'Waterproofing repair required',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $vendorQuote = MaintenanceVendorQuote::create([
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendor->id,
            'trade_title' => 'Terrace Membrane Waterproofing',
            'vendor_quote_number' => 'EST-2026-9901',
            'vendor_quote_date' => '2026-08-12',
            'scope_of_work' => 'Apply 3-coat polymer waterproofing membrane with 5-year warranty',
            'quoted_cost' => 8500.00,
            'status' => 'submitted',
        ]);

        $this->assertEquals('EST-2026-9901', $vendorQuote->vendor_quote_number);
        $this->assertEquals('2026-08-12', $vendorQuote->vendor_quote_date->format('Y-m-d'));
        $this->assertFalse($vendorQuote->fresh()->is_awarded);

        // Client quote
        $clientQuote = MaintenanceClientQuote::create([
            'maintenance_request_id' => $request->id,
            'quote_number' => 'QTE-2026-00099',
            'total_amount' => 11000.00,
            'owner_amount' => 11000.00,
            'status' => 'approved',
            'approved_at' => now(),
            'awarded_vendor_quote_ids' => [$vendorQuote->id],
        ]);

        // Issue Work Order
        $vendorQuote->update([
            'is_awarded' => true,
            'work_order_number' => 'WO-00099-9901',
            'work_order_issued_at' => now(),
            'status' => 'awarded',
        ]);

        $this->assertTrue($vendorQuote->fresh()->is_awarded);
        $this->assertEquals('WO-00099-9901', $vendorQuote->fresh()->work_order_number);
        $this->assertNotNull($vendorQuote->fresh()->work_order_issued_at);

        // Verify Work Order PDF Letter Generation
        $pdfService = app(\App\Domain\Maintenance\Services\MaintenanceWorkOrderPdfService::class);
        $media = $pdfService->generatePdf($vendorQuote, $clientQuote);

        $this->assertNotNull($media);
        $this->assertTrue($vendorQuote->fresh()->hasMedia('work_order_letter_pdf'));
        $this->assertFileExists($media->getPath());
    }

    public function test_repair_execution_tracking_and_audit_trigger_validation(): void
    {
        $roomType = RoomType::create(['name' => 'Balcony', 'slug' => 'balcony-' . uniqid()]);
        $roomDef = RoomDefinition::create(['room_type_id' => $roomType->id, 'name' => 'Kitchen Balcony', 'slug' => 'kitchen-balcony-' . uniqid()]);
        $room = PropertyRoom::create([
            'property_id' => $this->property->id,
            'room_definition_id' => $roomDef->id,
            'custom_name' => 'Kitchen Balcony',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'owner_id' => $this->ownerParty->id,
            'tenant_id' => $this->tenantParty->id,
            'title' => 'Balcony Drain Leakage',
            'description' => 'Water logging on the kitchen balcony during rains',
            'priority' => 'high',
            'reporter_type' => 'staff',
            'payer_type' => 'owner',
            'is_direct_vendor' => false,
            'status' => MaintenanceStatus::IN_PROGRESS,
            'assigned_inspector_id' => $this->inspector->id,
        ]);

        $item = MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'itemable_type' => PropertyRoom::class,
            'itemable_id' => $room->id,
            'issue_description' => 'Drain line clogged and leaking',
            'repair_action' => null,
            'status' => 'pending',
        ]);

        // Attach before photo
        $fakeImage = \Illuminate\Http\UploadedFile::fake()->image('before_repair.jpg');
        $item->addMedia($fakeImage)->toMediaCollection('issue_photos');

        // Verify that item is incomplete (no repair_action or repaired_photos)
        $this->assertFalse($item->hasMedia('repaired_photos'));
        $this->assertNull($item->repair_action);

        // Update item with repair resolution and after photos
        $item->update([
            'repair_action' => 'Unblocked drain and replaced pipe joint',
            'actual_cost' => 1500.00,
            'status' => 'completed',
        ]);
        $fakeAfterImage = \Illuminate\Http\UploadedFile::fake()->image('after_repair.jpg');
        $item->addMedia($fakeAfterImage)->toMediaCollection('repaired_photos');

        $this->assertTrue($item->fresh()->hasMedia('repaired_photos'));
        $this->assertEquals('completed', $item->fresh()->status);

        // Client quote with approval proof for audit trigger
        $clientQuote = MaintenanceClientQuote::create([
            'maintenance_request_id' => $request->id,
            'quote_number' => 'QTE-2026-00088',
            'total_amount' => 2000.00,
            'owner_amount' => 2000.00,
            'status' => 'approved',
            'approved_at' => now(),
        ]);
        $fakeProof = \Illuminate\Http\UploadedFile::fake()->create('approval.pdf', 100);
        $clientQuote->addMedia($fakeProof)->toMediaCollection('approval_proof_files');
        $request->update(['current_client_quote_id' => $clientQuote->id]);

        // Trigger audit
        $service = app(MaintenanceAuditTriggerService::class);
        $audit = $service->triggerAudit($request);

        $this->assertNotNull($audit);
        $this->assertEquals($audit->id, $request->fresh()->triggered_audit_id);
        $this->assertEquals(MaintenanceStatus::AUDIT_PENDING, $request->fresh()->status);
        $this->assertFalse($audit->fresh()->is_locked);

        // Approve audit
        $audit->update(['status' => \App\Domain\Audit\Enums\AuditStatus::APPROVED]);

        // Close ticket and lock audit
        $audit->update([
            'status' => \App\Domain\Audit\Enums\AuditStatus::APPROVED,
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by_id' => $this->inspector->id,
        ]);
        $request->update(['status' => MaintenanceStatus::CLOSED]);

        $this->assertTrue($audit->fresh()->is_locked);
        $this->assertEquals(\App\Domain\Audit\Enums\AuditStatus::APPROVED, $audit->fresh()->status);
        $this->assertEquals(MaintenanceStatus::CLOSED, $request->fresh()->status);
    }
}


