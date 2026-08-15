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
});
