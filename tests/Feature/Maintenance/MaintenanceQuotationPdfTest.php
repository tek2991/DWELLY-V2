<?php

use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceClientQuoteItem;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Services\MaintenanceQuotationPdfService;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('maintenance quotation pdf service generates valid pdf and increments version', function () {
    $owner = Party::create(['display_name' => 'John Owner', 'party_type' => 'individual']);
    $property = Property::create([
        'building_name' => 'Palm Heights Apt 402',
        'status' => 'active',
    ]);

    $ticket = MaintenanceRequest::create([
        'ticket_number' => 'MR-2026-00099',
        'title' => 'Bathroom Plumbing Repair',
        'description' => 'Severe leakage in main pipe',
        'property_id' => $property->id,
        'owner_party_id' => $owner->id,
        'payer_type' => 'owner',
        'severity' => 'urgent',
        'status' => \App\Domain\Maintenance\Enums\MaintenanceStatus::DRAFT,
        'is_direct_vendor' => false,
    ]);

    $quote = MaintenanceClientQuote::create([
        'maintenance_request_id' => $ticket->id,
        'quote_number' => 'QTE-2026-TEST1',
        'version' => 1,
        'total_amount' => 1500.00,
        'status' => 'draft',
    ]);

    MaintenanceClientQuoteItem::create([
        'maintenance_client_quote_id' => $quote->id,
        'description' => 'PVC Pipe Replacement & Labor',
        'quantity' => 1,
        'unit_price' => 1500.00,
        'total_price' => 1500.00,
    ]);

    $service = app(MaintenanceQuotationPdfService::class);
    $media1 = $service->generatePdf($quote);

    expect($media1)->not->toBeNull();
    expect($quote->fresh()->version)->toBe(1);
    expect($quote->fresh()->hasMedia('generated_quote_pdf'))->toBeTrue();

    // Regenerate PDF -> increments version
    $media2 = $service->generatePdf($quote);
    expect($quote->fresh()->version)->toBe(2);
    expect($quote->fresh()->getMedia('generated_quote_pdf')->count())->toBe(2);

    $user = \App\Models\User::factory()->create();

    expect($quote->fresh()->subtotal_amount)->toEqual(1500.00);
    expect($quote->fresh()->tax_amount)->toEqual(270.00);
    expect($quote->fresh()->total_amount)->toEqual(1770.00);

    // Verify rendered PDF view has tax components and rupee formatting
    $viewHtml = view('pdf.maintenance_quotation', [
        'quote' => $quote->fresh(),
        'ticket' => $ticket,
    ])->render();

    expect($viewHtml)->toContain('Tax Component');
    expect($viewHtml)->toContain('CGST');
    expect($viewHtml)->toContain('SGST');
    expect($viewHtml)->toContain('₹ 1,500.00');
    expect($viewHtml)->toContain('₹ 1,770.00');
    expect($viewHtml)->not->toContain('&#8377;');

    // Stream PDF
    $response = $this->actingAs($user)->get(route('billing.quotation.pdf', ['quote' => $quote->id]));
    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/pdf');

    // Download PDF
    $downloadResponse = $this->actingAs($user)->get(route('billing.quotation.pdf.download', ['quote' => $quote->id]));
    $downloadResponse->assertOk();
    $downloadResponse->assertHeader('Content-Type', 'application/pdf');
});

test('contractor work order pdf streaming and downloading routes work properly', function () {
    $vendorParty = Party::create(['display_name' => 'Apex Electricals', 'party_type' => 'organization']);
    $property = Property::create(['building_name' => 'Sunrise Heights 101', 'status' => 'active']);

    $ticket = MaintenanceRequest::create([
        'ticket_number' => 'MR-2026-WO01',
        'title' => 'Electrical Short Circuit',
        'property_id' => $property->id,
        'status' => \App\Domain\Maintenance\Enums\MaintenanceStatus::IN_PROGRESS,
        'payer_type' => 'owner',
        'is_direct_vendor' => false,
    ]);

    $vendorQuote = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::create([
        'maintenance_request_id' => $ticket->id,
        'vendor_party_id' => $vendorParty->id,
        'trade_title' => 'Main Distribution Board Repair',
        'quoted_cost' => 4500.00,
        'work_order_number' => 'WO-2026-WO01-101',
        'is_awarded' => true,
        'status' => 'awarded',
    ]);

    $user = \App\Models\User::factory()->create();

    // Stream Work Order PDF
    $streamResponse = $this->actingAs($user)->get(route('billing.work_order.pdf', ['vendorQuote' => $vendorQuote->id]));
    $streamResponse->assertOk();
    $streamResponse->assertHeader('Content-Type', 'application/pdf');

    // Download Work Order PDF
    $downloadResponse = $this->actingAs($user)->get(route('billing.work_order.pdf.download', ['vendorQuote' => $vendorQuote->id]));
    $downloadResponse->assertOk();
    $downloadResponse->assertHeader('Content-Type', 'application/pdf');
});

test('maintenance quotation pdf renders multiple defect items per line item', function () {
    $owner = Party::create(['display_name' => 'Jane Owner', 'party_type' => 'individual']);
    $property = Property::create(['building_name' => 'Lotus Enclave 303', 'status' => 'active']);

    $ticket = MaintenanceRequest::create([
        'ticket_number' => 'MR-2026-MULTI-DEF',
        'title' => 'Multi-Area Refurbishment',
        'property_id' => $property->id,
        'owner_party_id' => $owner->id,
        'payer_type' => 'owner',
        'status' => \App\Domain\Maintenance\Enums\MaintenanceStatus::DRAFT,
        'is_direct_vendor' => false,
    ]);

    $defect1 = \App\Domain\Maintenance\Models\MaintenanceRequestItem::create([
        'maintenance_request_id' => $ticket->id,
        'issue_description' => 'Kitchen Cabinet Hinge Repair',
    ]);

    $defect2 = \App\Domain\Maintenance\Models\MaintenanceRequestItem::create([
        'maintenance_request_id' => $ticket->id,
        'issue_description' => 'Balcony Sliding Door Alignment',
    ]);

    $quote = MaintenanceClientQuote::create([
        'maintenance_request_id' => $ticket->id,
        'quote_number' => 'QTE-2026-MULTI-DEF',
        'version' => 1,
        'status' => 'draft',
    ]);

    MaintenanceClientQuoteItem::create([
        'maintenance_client_quote_id' => $quote->id,
        'maintenance_request_item_ids' => [$defect1->id, $defect2->id],
        'description' => 'Carpentry & Door Alignment Package',
        'quantity' => 1,
        'unit_price' => 4500.00,
        'total_price' => 4500.00,
    ]);

    $viewHtml = view('pdf.maintenance_quotation', [
        'quote' => $quote->fresh(),
        'ticket' => $ticket,
    ])->render();

    expect($viewHtml)->toContain('Carpentry &amp; Door Alignment Package');
    expect($viewHtml)->toContain('Kitchen Cabinet Hinge Repair');
    expect($viewHtml)->toContain('Balcony Sliding Door Alignment');
});

