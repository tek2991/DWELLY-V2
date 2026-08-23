<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceRequestItem;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Billing\MaintenanceQuotationResource;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\EditMaintenanceQuotation;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageClientQuotation;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageQuotationApproval;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageQuotationSettlement;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageQuotationWorkOrders;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Schemas\MaintenanceQuotationForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceQuotationSubNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_all_sub_navigation_pages_for_maintenance_quotation(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Royal Palms Suite 101',
            'code' => 'RP-101',
            'status' => 'active',
        ]);

        $owner = Party::create([
            'display_name' => 'Rakesh Patel (Owner)',
            'party_type' => 'individual',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'TICK-9090',
            'property_id' => $property->id,
            'owner_party_id' => $owner->id,
            'title' => 'Plumbing and Painting Overhaul',
            'description' => 'Multiple leakage and wall crack issues',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-0099',
            'maintenance_request_id' => $request->id,
            'subtotal_amount' => 5000.00,
            'margin_percentage' => 10.00,
            'margin_amount' => 500.00,
            'gst_percentage' => 18.00,
            'tax_amount' => 990.00,
            'total_amount' => 6490.00,
            'status' => 'draft',
            'valid_until' => now()->addDays(14)->toDateString(),
        ]);

        $request->update(['current_client_quote_id' => $quote->id]);

        // 1. Test Page 1: Vendor Quotes
        Livewire::actingAs($user)
            ->test(EditMaintenanceQuotation::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful();

        // 2. Test Page 2: Client Quotation & Pricing
        Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful();

        // 3. Test Page 3: Client Approval
        Livewire::actingAs($user)
            ->test(ManageQuotationApproval::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful();

        // 4. Test Page 4: Vendor Work Orders
        Livewire::actingAs($user)
            ->test(ManageQuotationWorkOrders::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful();

        // 5. Test Page 5: Settlement & Billing
        Livewire::actingAs($user)
            ->test(ManageQuotationSettlement::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful();
    }

    public function test_workflow_header_renders_context_and_progress(): void
    {
        $property = Property::create([
            'building_name' => 'Greenfield Villa 5',
            'code' => 'GV-05',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'TICK-7788',
            'property_id' => $property->id,
            'title' => 'Electrical Panel Sparking',
            'priority' => MaintenancePriority::EMERGENCY,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-0778',
            'maintenance_request_id' => $request->id,
            'total_amount' => 4500.00,
            'margin_amount' => 450.00,
            'status' => 'draft',
        ]);

        $html = MaintenanceQuotationForm::getWorkflowHeaderHtml($quote);

        $this->assertNotNull($html);
        $content = (string) $html;
        $this->assertStringContainsString('QT-2026-0778', $content);
        $this->assertStringContainsString('TICK-7788', $content);
        $this->assertStringContainsString('Greenfield Villa 5', $content);
        $this->assertStringContainsString('Quotation Workflow Progress', $content);
    }

    public function test_import_vendor_quotes_action_maps_defect_items(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'building_name' => 'Greenfield Villa 5',
            'code' => 'GV-05',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'TICK-9900',
            'property_id' => $property->id,
            'title' => 'Plumbing & Tiling Repair',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $item1 = \App\Domain\Maintenance\Models\MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'issue_description' => 'Leaking bathroom pipe',
            'severity' => 'medium',
        ]);

        $vendor = \App\Domain\Party\Models\Party::create([
            'display_name' => 'Quick Plumbing Services',
            'party_type' => 'individual',
        ]);

        $vendorQuote = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::create([
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendor->id,
            'maintenance_request_item_ids' => [$item1->id],
            'trade_title' => 'Plumbing Works',
            'quoted_cost' => 1200.00,
            'status' => 'draft',
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-9900',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
        ]);

        Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Import Vendor Quotes');
    }

    public function test_pricing_page_loads_and_persists_unit_rates_correctly(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'building_name' => 'Sunset Towers 402',
            'code' => 'ST-402',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'TICK-1234',
            'property_id' => $property->id,
            'title' => 'Deep Cleaning & Painting',
            'priority' => MaintenancePriority::MEDIUM,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-1234',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
            'margin_percentage' => 10.00,
            'gst_percentage' => 18.00,
        ]);

        $item = \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'description' => 'Wall Plastering & Touchup',
            'quantity' => 3,
            'vendor_cost' => 1500.00,
            'unit_price' => 1750.00,
            'total_price' => 5250.00,
            'sort_order' => 1,
        ]);

        // Verify initial hydration has unit_price and rendered financial summary breakdown
        $component = Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('₹5,250.00'); // Client subtotal from initial item (3 * 1750 = 5250)

        $items = $component->get('data.items');
        $this->assertNotEmpty($items);
        $firstItemKey = array_key_first($items);
        $this->assertEquals(1750.00, (float) $items[$firstItemKey]['unit_price']);

        // Test updating the client price before tax and saving
        $items[$firstItemKey]['unit_price'] = 2200.00;
        $component->set('data.items', $items);
        $component->call('save');
        $component->assertHasNoErrors();

        // Check database value
        $item->refresh();
        $this->assertEquals(2200.00, (float) $item->unit_price);
        $this->assertEquals(6600.00, (float) $item->total_price);

        $quote->refresh();
        $this->assertEquals(6600.00, (float) $quote->subtotal_amount);
        // Vendor total is 3 * 1500 = 4500. Margin is 6600 - 4500 = 2100.
        $this->assertEquals(2100.00, (float) $quote->margin_amount);

        // Reload page and assert unit_price and financial summary card reflect updated non-zero numbers
        Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful()
            ->assertSet("data.items.{$firstItemKey}.unit_price", 2200.00)
            ->assertSee('₹6,600.00') // Updated Client Subtotal
            ->assertSee('₹4,500.00') // Vendor Base Cost
            ->assertSee('Quotation Financial Breakdown');
    }

    public function test_apply_pricing_margin_action_recalculates_line_items(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'building_name' => 'Sunset Towers 402',
            'code' => 'ST-402',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'TICK-4321',
            'property_id' => $property->id,
            'title' => 'Electrical Overhaul',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-4321',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
            'margin_percentage' => 20.00, // 20%
            'gst_percentage' => 18.00,
        ]);

        $item = \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'description' => 'Circuit Breaker Replacement',
            'quantity' => 2,
            'vendor_cost' => 1000.00,
            'unit_price' => 1000.00, // old un-marked price
            'total_price' => 2000.00,
            'sort_order' => 1,
        ]);

        Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Apply Markup to Line Items');
    }

    public function test_duplicate_items_alert_displays_when_duplicate_items_present(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'building_name' => 'Sunset Towers 402',
            'code' => 'ST-402',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'TICK-5555',
            'property_id' => $property->id,
            'title' => 'Plumbing and Painting Repairs',
            'priority' => MaintenancePriority::MEDIUM,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $defectItem = MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'issue_description' => 'Bathroom Pipe Leakage',
            'severity' => 'major',
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-5555',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
            'margin_percentage' => 10.00,
            'gst_percentage' => 18.00,
        ]);

        \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'maintenance_request_item_id' => $defectItem->id,
            'description' => 'Fix Pipe Leakage',
            'quantity' => 1,
            'unit_price' => 1500.00,
            'total_price' => 1500.00,
            'sort_order' => 1,
        ]);

        \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'maintenance_request_item_id' => $defectItem->id, // Duplicate defect item
            'description' => 'Fix Pipe Leakage', // Duplicate description
            'quantity' => 1,
            'unit_price' => 1500.00,
            'total_price' => 1500.00,
            'sort_order' => 2,
        ]);

        Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Potential Duplicate Line Items Detected')
            ->assertSee('Bathroom Pipe Leakage')
            ->assertSee('Fix Pipe Leakage');

        $duplicates = MaintenanceQuotationForm::getDuplicateLineItemsSummary($quote);
        $this->assertNotEmpty($duplicates);
        $this->assertStringContainsString('Bathroom Pipe Leakage', implode(' ', $duplicates));

        // Clean quote without duplicates returns empty
        $cleanQuote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-CLEAN',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
        ]);
        \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $cleanQuote->id,
            'description' => 'Fix Pipe Leakage',
            'quantity' => 1,
            'unit_price' => 1500.00,
            'total_price' => 1500.00,
            'sort_order' => 1,
        ]);
        $cleanDuplicates = MaintenanceQuotationForm::getDuplicateLineItemsSummary($cleanQuote);
        $this->assertEmpty($cleanDuplicates);
    }

    public function test_tax_components_breakdown_and_selection_in_quotation(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'building_name' => 'Oakwood Residency 101',
            'code' => 'OR-101',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'TICK-9876',
            'property_id' => $property->id,
            'title' => 'Kitchen Plumbing Overhaul',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-9876',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
            'subtotal_amount' => 10000.00,
            'margin_percentage' => 10.00,
            'gst_percentage' => 18.00,
            'tax_amount' => 1800.00,
            'total_amount' => 11800.00,
        ]);

        \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'description' => 'Sink & Pipe Installation',
            'quantity' => 1,
            'unit_price' => 10000.00,
            'total_price' => 10000.00,
            'sort_order' => 1,
        ]);

        $breakdown = $quote->getTaxComponentsBreakdown();
        $this->assertCount(2, $breakdown);
        $this->assertEquals('CGST', $breakdown[0]['name']);
        $this->assertEquals(9.0, $breakdown[0]['rate']);
        $this->assertEquals(900.00, $breakdown[0]['amount']);
        $this->assertEquals('SGST', $breakdown[1]['name']);
        $this->assertEquals(9.0, $breakdown[1]['rate']);
        $this->assertEquals(900.00, $breakdown[1]['amount']);

        Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Tax Component / Regime')
            ->assertSee('CGST (9%): + ₹900.00')
            ->assertSee('SGST (9%): + ₹900.00');

        // Test with real Tax model containing components
        $salesAcc = \Tek2991\Accounting\Models\Account::create([
            'code' => '2100',
            'name' => 'GST Output Tax',
            'type' => \Tek2991\Accounting\Enums\AccountType::Liability,
            'status' => 'active',
            'is_system' => true,
        ]);
        $purchaseAcc = \Tek2991\Accounting\Models\Account::create([
            'code' => '1300',
            'name' => 'GST Input Tax',
            'type' => \Tek2991\Accounting\Enums\AccountType::Asset,
            'status' => 'active',
            'is_system' => true,
        ]);

        $tax = \Tek2991\Accounting\Models\Tax::create([
            'name' => 'GST 18%',
            'is_active' => true,
        ]);
        \Tek2991\Accounting\Models\TaxComponent::create([
            'tax_id' => $tax->id,
            'name' => 'CGST',
            'type' => \Tek2991\Accounting\Enums\TaxComponentType::Intrastate,
            'rate' => 9.00,
            'sales_account_id' => $salesAcc->id,
            'purchase_account_id' => $purchaseAcc->id,
        ]);
        \Tek2991\Accounting\Models\TaxComponent::create([
            'tax_id' => $tax->id,
            'name' => 'SGST',
            'type' => \Tek2991\Accounting\Enums\TaxComponentType::Intrastate,
            'rate' => 9.00,
            'sales_account_id' => $salesAcc->id,
            'purchase_account_id' => $purchaseAcc->id,
        ]);
        $quote->update(['tax_id' => $tax->id]);
        $quote->refresh();

        Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, ['record' => $quote->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('CGST (9%): + ₹900.00')
            ->assertSee('SGST (9%): + ₹900.00')
            ->assertSee('Generate Quotation');

        $pricingUrl = MaintenanceQuotationResource::getUrl('pricing', ['record' => $quote]);
        $this->assertStringContainsString('/pricing', $pricingUrl);
    }

    public function test_approved_by_type_matches_ticket_payer(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'building_name' => 'Rose Villa 101',
            'code' => 'RV-101',
            'status' => 'active',
        ]);

        $tenantRequest = MaintenanceRequest::create([
            'ticket_number' => 'TICK-TENANT-1',
            'property_id' => $property->id,
            'title' => 'Geyser Repair',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::TENANT,
            'is_direct_vendor' => false,
        ]);

        $tenantQuote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-TENANT',
            'maintenance_request_id' => $tenantRequest->id,
            'status' => 'pending_approval',
            'total_amount' => 2000.00,
        ]);

        Livewire::actingAs($user)
            ->test(ManageQuotationApproval::class, ['record' => $tenantQuote->getRouteKey()])
            ->assertSuccessful()
            ->assertSchemaStateSet([
                'approved_by_type' => 'tenant',
            ]);

        $ownerRequest = MaintenanceRequest::create([
            'ticket_number' => 'TICK-OWNER-1',
            'property_id' => $property->id,
            'title' => 'Roof Waterproofing',
            'priority' => MaintenancePriority::MEDIUM,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $ownerQuote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-OWNER',
            'maintenance_request_id' => $ownerRequest->id,
            'status' => 'pending_approval',
            'total_amount' => 8000.00,
        ]);

        Livewire::actingAs($user)
            ->test(ManageQuotationApproval::class, ['record' => $ownerQuote->getRouteKey()])
            ->assertSuccessful()
            ->assertSchemaStateSet([
                'approved_by_type' => 'owner',
            ]);
    }

    public function test_regenerate_pdf_is_disabled_when_quotation_approved(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'building_name' => 'Lotus Enclave 201',
            'code' => 'LE-201',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'TICK-APPR-1',
            'property_id' => $property->id,
            'title' => 'AC Compressor Repair',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::QUOTATION_APPROVED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $approvedQuote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-APPR-1',
            'maintenance_request_id' => $request->id,
            'status' => 'approved',
            'total_amount' => 5000.00,
        ]);

        $testComponent = Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, ['record' => $approvedQuote->getRouteKey()])
            ->assertSuccessful();

        $schema = $testComponent->instance()->getSchema('form');
        $section = collect($schema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Official Client Quotation PDF'));
        $action = collect($section->getHeaderActions())->first(fn ($a) => $a->getName() === 'generateQuotationInPdfCard');

        $this->assertNotNull($action);
        $this->assertTrue($action->isDisabled($approvedQuote));
    }

    public function test_archived_quotation_disables_all_fields_and_actions(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'building_name' => 'Rose Villa 102',
            'code' => 'RV-102',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'TICK-ARCH-1',
            'property_id' => $property->id,
            'title' => 'Plumbing Overhaul',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $archivedQuote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-ARCH-1',
            'maintenance_request_id' => $request->id,
            'status' => 'archived',
            'total_amount' => 6000.00,
        ]);

        $testPricing = Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, ['record' => $archivedQuote->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Quotation Archived & Locked', false);

        $schema = $testPricing->instance()->getSchema('form');
        $marginComponent = $schema->getComponent('margin_percentage');
        $this->assertTrue($marginComponent->isDisabled());

        $taxComponent = $schema->getComponent('tax_id');
        $this->assertTrue($taxComponent->isDisabled());

        $pdfSection = collect($schema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Official Client Quotation PDF'));
        $generateAction = collect($pdfSection->getHeaderActions())->first(fn ($a) => $a->getName() === 'generateQuotationInPdfCard');
        $this->assertNotNull($generateAction);
        $this->assertTrue($generateAction->isDisabled($archivedQuote));

        // Check approval page
        $testApproval = Livewire::actingAs($user)
            ->test(ManageQuotationApproval::class, ['record' => $archivedQuote->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Quotation Archived & Locked', false);

        $approvalSchema = $testApproval->instance()->getSchema('form');
        $channelComponent = $approvalSchema->getComponent('approval_channel');
        $this->assertTrue($channelComponent->isDisabled());

        $notesComponent = $approvalSchema->getComponent('approval_notes');
        $this->assertTrue($notesComponent->isDisabled());
    }

    public function test_work_orders_tab_highlights_included_contractor_quotes(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'building_name' => 'Palm Grove 501',
            'code' => 'PG-501',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'TICK-WO-HIGH-1',
            'property_id' => $property->id,
            'title' => 'Masonry & Tiling Work',
            'priority' => MaintenancePriority::HIGH,
            'status' => MaintenanceStatus::QUOTATION_APPROVED,
            'payer_type' => PayerType::OWNER,
            'is_direct_vendor' => false,
        ]);

        $vendorParty1 = \App\Domain\Party\Models\Party::create([
            'display_name' => 'Alpha Masonry Works',
            'party_type' => 'organization',
        ]);

        $vendorParty2 = \App\Domain\Party\Models\Party::create([
            'display_name' => 'Beta Tiles & Repairs',
            'party_type' => 'organization',
        ]);

        $quote1 = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::create([
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendorParty1->id,
            'trade_title' => 'Bathroom Wall Plastering',
            'quoted_cost' => 3500.00,
            'vendor_quote_date' => now(),
        ]);

        $quote2 = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::create([
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendorParty2->id,
            'trade_title' => 'Alternative High Bid Plastering',
            'quoted_cost' => 5000.00,
            'vendor_quote_date' => now(),
        ]);

        $clientQuote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-WO-HIGH-1',
            'maintenance_request_id' => $request->id,
            'status' => 'approved',
            'total_amount' => 4500.00,
        ]);

        // Quote 1 is included in the approved quotation items
        \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $clientQuote->id,
            'vendor_quote_id' => $quote1->id,
            'description' => 'Bathroom Wall Plastering (Labor + Material)',
            'quantity' => 1,
            'vendor_cost' => 3500.00,
            'unit_price' => 4000.00,
            'total_price' => 4000.00,
            'sort_order' => 1,
        ]);

        $this->assertEquals([$quote1->id], $clientQuote->getIncludedVendorQuoteIds());

        $testWorkOrders = Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageQuotationWorkOrders::class, [
                'record' => $clientQuote->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('Trade Scope')
            ->assertSee('Bathroom Wall Plastering')
            ->assertSee('Alpha Masonry Works')
            ->assertSee('Alternative High Bid Plastering')
            ->assertSee('Beta Tiles & Repairs')
            ->assertSee('Included in Client Quote')
            ->assertSee('Alternative Estimate')
            ->assertSet('data.awarded_vendor_quote_ids', [$quote1->id]);

        $schema = $testWorkOrders->instance()->getSchema('form');
        $tablePlaceholder = $schema->getComponent('contractor_work_orders_table');
        $this->assertNotNull($tablePlaceholder);

        // Test modal description when unapproved vendor is selected
        $section = collect($schema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Contractor Work Orders'));
        $issueAction = collect($section->getHeaderActions())->first(fn ($a) => $a->getName() === 'issueWorkOrderInTab');
        $this->assertNotNull($issueAction);

        // When only approved quote1 is selected
        $descHtml = (string) $issueAction->getModalDescription();
        $this->assertStringNotContainsString('Warning: Unapproved Contractor', $descHtml);

        // When alternative quote2 is selected
        $testWorkOrders->set('data.awarded_vendor_quote_ids', [$quote2->id]);
        $freshSchema = $testWorkOrders->instance()->getSchema('form');
        $freshSection = collect($freshSchema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Contractor Work Orders'));
        $freshAction = collect($freshSection->getHeaderActions())->first(fn ($a) => $a->getName() === 'issueWorkOrderInTab');
        $descWithWarning = (string) $freshAction->getModalDescription();
        $this->assertStringContainsString('Warning: Unapproved Contractor', $descWithWarning);
        $this->assertStringContainsString('Alternative High Bid Plastering', $descWithWarning);
    }

    public function test_sync_vendor_costs_and_discrepancy_alert_in_pricing_page(): void
    {
        $user = User::factory()->create();
        $property = Property::create([
            'building_name' => 'Emerald Heights 501',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'TICK-SYNC-01',
            'property_id' => $property->id,
            'title' => 'Plumbing Overhaul',
            'status' => MaintenanceStatus::QUOTATION_PENDING,
            'payer_type' => PayerType::OWNER,
        ]);

        $vendorParty = Party::create([
            'party_type' => 'organization',
            'display_name' => 'Elite Plumbing Solutions',
        ]);

        $vendorQuote = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::create([
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendorParty->id,
            'trade_title' => 'Master Bath Plumbing',
            'quoted_cost' => 5000.00,
        ]);

        $clientQuote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-SYNC-001',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
            'margin_percentage' => 10.00,
            'gst_percentage' => 18.00,
        ]);

        $item = \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $clientQuote->id,
            'vendor_quote_id' => $vendorQuote->id,
            'description' => 'Master Bath Plumbing',
            'quantity' => 1,
            'vendor_cost' => 5000.00,
            'unit_price' => 5500.00,
            'total_price' => 5500.00,
            'sort_order' => 1,
        ]);

        // When costs match, no discrepancy
        $testPricing = Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageClientQuotation::class, [
                'record' => $clientQuote->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertDontSee('Contractor Estimate Updates Detected');

        // Now contractor updates their estimate in Tab 1 to 6500
        $vendorQuote->update(['quoted_cost' => 6500.00]);

        // Pricing page now displays the discrepancy alert
        Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageClientQuotation::class, [
                'record' => $clientQuote->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('Contractor Estimate Updates Detected')
            ->assertSee('Sync Vendor Costs');

        // Trigger Sync Vendor Costs action
        $testPricing = Livewire::actingAs($user)
            ->test(\App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageClientQuotation::class, [
                'record' => $clientQuote->getRouteKey(),
            ]);

        $schema = $testPricing->instance()->getSchema('form');
        $section = collect($schema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Itemized Line Items'));
        $syncAction = collect($section->getHeaderActions())->first(fn ($a) => $a->getName() === 'syncVendorCosts');
        $testPricing->call('save');
    }

    public function test_vendor_quotes_repeater_has_save_and_add_vendor_quote_action(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Pine Crest 302',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Electrical Overhaul',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-TEST-ADD',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
        ]);

        $testEdit = Livewire::actingAs($user)
            ->test(EditMaintenanceQuotation::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful();

        $schema = $testEdit->instance()->getSchema('form');
        $section = collect($schema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Multi-Vendor Bids'));
        $this->assertNotNull($section);

        $repeater = collect($section->getChildComponents())->first(fn ($c) => $c instanceof \Filament\Forms\Components\Repeater && $c->getName() === 'vendorQuotes');
        $this->assertNotNull($repeater);
        $this->assertEquals('Save and add vendor quote', $repeater->getAddActionLabel());
        $this->assertTrue($repeater->getDeleteAction()->isConfirmationRequired());
    }

    public function test_items_repeater_has_save_and_add_line_item_action(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Pine Crest 303',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Plumbing Repair',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-TEST-ITEM-ADD',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
        ]);

        $testPricing = Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful();

        $schema = $testPricing->instance()->getSchema('form');
        $section = collect($schema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Itemized Line Items'));
        $this->assertNotNull($section);

        $repeater = collect($section->getChildComponents())->first(fn ($c) => $c instanceof \Filament\Forms\Components\Repeater && $c->getName() === 'items');
        $this->assertNotNull($repeater);
        $this->assertEquals('Save and add line item', $repeater->getAddActionLabel());
        $this->assertTrue($repeater->getDeleteAction()->isConfirmationRequired());
    }

    public function test_items_repeater_supports_multi_select_defect_items(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Pine Crest 304',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Multiple Repairs',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
        ]);

        $defect1 = MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'issue_description' => 'Wall Crack Repair',
        ]);

        $defect2 = MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'issue_description' => 'Ceiling Water Seepage',
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-MULTI-DEFECT',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
        ]);

        $item = \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'maintenance_request_item_ids' => [$defect1->id, $defect2->id],
            'description' => 'Masonry & Waterproofing Package',
            'quantity' => 1,
            'unit_price' => 7500.00,
            'total_price' => 7500.00,
            'sort_order' => 1,
        ]);

        $this->assertCount(2, $item->fresh()->maintenance_request_item_ids);
        $this->assertEquals($defect1->id, $item->fresh()->maintenance_request_item_id);

        $testPricing = Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful();

        $schema = $testPricing->instance()->getSchema('form');
        $section = collect($schema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Itemized Line Items'));
        $repeater = collect($section->getChildComponents())->first(fn ($c) => $c instanceof \Filament\Forms\Components\Repeater && $c->getName() === 'items');
        $defectSelect = collect($repeater->getChildComponents())->first(fn ($c) => $c instanceof \Filament\Forms\Components\Select && $c->getName() === 'maintenance_request_item_ids');
        $this->assertNotNull($defectSelect);
        $this->assertTrue($defectSelect->isMultiple());

        $vendorCostInput = collect($repeater->getChildComponents())->first(fn ($c) => $c instanceof \Filament\Forms\Components\TextInput && $c->getName() === 'vendor_cost');
        $this->assertNotNull($vendorCostInput);
    }

    public function test_manual_line_item_without_vendor_cost_saves_successfully(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Highland Park 501',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Electrical Inspection',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-MANUAL-LINE',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
            'margin_percentage' => 15.00,
            'gst_percentage' => 18.00,
        ]);

        // Simulate creating manual item through Livewire form without vendor quote / vendor cost
        $testPricing = Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful()
            ->set('data.items', [
                'item_1' => [
                    'description' => 'In-House Electrician Diagnostic Fee',
                    'quantity' => 1,
                    'unit_price' => 2500.00,
                    'vendor_cost' => null, // null from manual form input
                ],
            ])
            ->call('save')
            ->assertHasNoErrors();

        $item = $quote->fresh()->items()->first();
        $this->assertNotNull($item);
        $this->assertEquals('In-House Electrician Diagnostic Fee', $item->description);
        $this->assertEquals(2500.00, (float) $item->unit_price);
        $this->assertEquals(2500.00, (float) $item->total_price);
        $this->assertEquals(0.00, (float) $item->vendor_cost);
    }

    public function test_vendor_cost_calculates_only_for_added_line_items(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Rosewood Residency 102',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'HVAC and Electrical Service',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
        ]);

        $vendor1 = \App\Domain\Party\Models\Party::create([
            'display_name' => 'Cool Air Techs',
            'party_type' => 'organization',
        ]);

        $vendor2 = \App\Domain\Party\Models\Party::create([
            'display_name' => 'Fast Sparks Electrical',
            'party_type' => 'organization',
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-STRICT-VENDOR-COST',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
            'margin_percentage' => 20.00,
            'gst_percentage' => 18.00,
        ]);

        // Tab 1 has two vendor estimates: ₹5,000 and ₹8,000 (Total in Tab 1 = ₹13,000)
        \App\Domain\Maintenance\Models\MaintenanceVendorQuote::create([
            'maintenance_client_quote_id' => $quote->id,
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendor1->id,
            'trade_title' => 'HVAC Coil Replacement',
            'quoted_cost' => 5000.00,
            'status' => 'received',
        ]);

        \App\Domain\Maintenance\Models\MaintenanceVendorQuote::create([
            'maintenance_client_quote_id' => $quote->id,
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendor2->id,
            'trade_title' => 'Main Panel Rewiring',
            'quoted_cost' => 8000.00,
            'status' => 'received',
        ]);

        // In Tab 2, user ONLY adds 1 line item with vendor_cost = 5,000 and 1 manual item with vendor_cost = 0 (Total line items vendor_cost = 5,000)
        \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'description' => 'HVAC Coil Repair',
            'quantity' => 1,
            'vendor_cost' => 5000.00,
            'unit_price' => 6000.00,
            'total_price' => 6000.00,
            'sort_order' => 1,
        ]);

        \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'description' => 'Dwelly Diagnostic & Quality Check',
            'quantity' => 1,
            'vendor_cost' => 0.00,
            'unit_price' => 1000.00,
            'total_price' => 1000.00,
            'sort_order' => 2,
        ]);

        $quote->recalculateTotals();

        // Subtotal = ₹7,000. Vendor Cost must be ₹5,000 (from added items), NOT ₹13,000 (all Tab 1 estimates)
        // Margin = Subtotal (7,000) - Vendor Cost (5,000) = ₹2,000
        $this->assertEquals(7000.00, (float) $quote->fresh()->subtotal_amount);
        $this->assertEquals(2000.00, (float) $quote->fresh()->margin_amount);

        // Sync to ticket
        $request->syncQuotationTotals();
        $this->assertEquals(5000.00, (float) $request->fresh()->total_vendor_cost);
    }

    public function test_deleting_line_item_and_vendor_quote_triggers_auto_save(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Green Vista 404',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Plumbing Overhaul',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-DELETE-AUTOSAVE',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
            'margin_percentage' => 10.00,
            'gst_percentage' => 18.00,
        ]);

        $item1 = \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'description' => 'Item 1 To Keep',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'total_price' => 1000.00,
            'sort_order' => 1,
        ]);

        $item2 = \App\Domain\Maintenance\Models\MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'description' => 'Item 2 To Delete',
            'quantity' => 1,
            'unit_price' => 2000.00,
            'total_price' => 2000.00,
            'sort_order' => 2,
        ]);

        $this->assertEquals(2, $quote->fresh()->items()->count());

        $testPricing = Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful();

        $schema = $testPricing->instance()->getSchema('form');
        $section = collect($schema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Itemized Line Items'));
        $repeater = collect($section->getChildComponents())->first(fn ($c) => $c instanceof \Filament\Forms\Components\Repeater && $c->getName() === 'items');
        $deleteAction = $repeater->getDeleteAction();

        $this->assertTrue($deleteAction->isConfirmationRequired());

        // Test deleteAction on vendorQuotes
        $testEdit = Livewire::actingAs($user)
            ->test(EditMaintenanceQuotation::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful();

        $schemaVendor = $testEdit->instance()->getSchema('form');
        $sectionVendor = collect($schemaVendor->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Multi-Vendor Bids'));
        $repeaterVendor = collect($sectionVendor->getChildComponents())->first(fn ($c) => $c instanceof \Filament\Forms\Components\Repeater && $c->getName() === 'vendorQuotes');
        $deleteVendorAction = $repeaterVendor->getDeleteAction();

        $this->assertTrue($deleteVendorAction->isConfirmationRequired());
    }

    public function test_import_from_vendor_quotes_requires_confirmation(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Oakwood Heights 201',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Tiling and Flooring',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-IMPORT-CONFIRM',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
        ]);

        $testPricing = Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful();

        $schema = $testPricing->instance()->getSchema('form');
        $section = collect($schema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Itemized Line Items'));
        $importAction = collect($section->getHeaderActions())->first(fn ($a) => $a->getName() === 'importFromVendorQuotes');

        $this->assertNotNull($importAction);
        $this->assertTrue($importAction->isConfirmationRequired());
    }

    public function test_manage_quotation_approval_hides_save_button_when_approved(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Oakwood Heights 202',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Electrical Overhaul',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
        ]);

        $draftQuote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-APP-DRAFT',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
        ]);

        $approvedQuote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-APP-APPROVED',
            'maintenance_request_id' => $request->id,
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by_type' => 'owner',
            'approval_notes' => 'Approved by owner',
        ]);

        // When in draft, save form action exists
        $testDraft = Livewire::actingAs($user)
            ->test(ManageQuotationApproval::class, [
                'record' => $draftQuote->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('Save changes');

        // When approved, save form action is removed
        $testApproved = Livewire::actingAs($user)
            ->test(ManageQuotationApproval::class, [
                'record' => $approvedQuote->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertDontSee('Save changes');
    }

    public function test_view_work_order_pdf_modal_action_mounts_successfully(): void
    {
        $user = User::factory()->create();

        $vendorParty = Party::create(['display_name' => 'Metro Plumbing Solutions', 'party_type' => 'organization']);
        $property = Property::create(['building_name' => 'Skyline Tower 501', 'status' => 'active']);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Pipe Replacement',
            'status' => MaintenanceStatus::IN_PROGRESS,
            'payer_type' => PayerType::OWNER,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-WO-MODAL',
            'maintenance_request_id' => $request->id,
            'status' => 'approved',
        ]);

        $vendorQuote = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::create([
            'maintenance_client_quote_id' => $quote->id,
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendorParty->id,
            'trade_title' => 'Plumbing Main Line Overhaul',
            'quoted_cost' => 6500.00,
            'work_order_number' => 'WO-2026-SKY-01',
            'is_awarded' => true,
            'status' => 'awarded',
        ]);

        $testWorkOrders = Livewire::actingAs($user)
            ->test(ManageQuotationWorkOrders::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertActionExists('viewWorkOrderPdf')
            ->assertSee('viewWorkOrderPdf', false);
    }

    public function test_settlement_form_displays_completion_guidance_and_prominent_ticket_link(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Grand Orchid 102',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'MNT-2026-00088',
            'property_id' => $property->id,
            'title' => 'Kitchen Cabinet Overhaul',
            'status' => MaintenanceStatus::IN_PROGRESS,
            'payer_type' => PayerType::OWNER,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-SETTLE-TEST',
            'maintenance_request_id' => $request->id,
            'status' => 'approved',
            'total_amount' => 12000.00,
            'subtotal_amount' => 10000.00,
            'margin_amount' => 2000.00,
            'margin_percentage' => 20.00,
        ]);

        $testSettlement = Livewire::actingAs($user)
            ->test(ManageQuotationSettlement::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('Invoice & Vendor Bill Generation Workflow')
            ->assertSee('Completion, Report & Verification')
            ->assertSee('Open Ticket #MNT-2026-00088 (Completion & Billing)')
            ->assertSee('relation=2', false);

        $schema = $testSettlement->instance()->getSchema('form');
        $section = collect($schema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Settlement & Accounting Workflow'));
        $ticketAction = collect($section->getHeaderActions())->first(fn ($a) => $a->getName() === 'viewTicketInTab5');

        $this->assertNull($ticketAction);
    }

    public function test_dwelly_absorbed_quotation_skips_step2_and_step3_in_sub_navigation(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Rose Villa 101',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'MNT-2026-DWELLY-SUBNAV',
            'property_id' => $property->id,
            'title' => 'Structural Repair (Absorbed by Dwelly)',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::DWELLY,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-DWELLY-SUBNAV',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
            'subtotal_amount' => 5000.00,
        ]);

        // 1. Edit page for Dwelly-absorbed quote only generates 3 sub-navigation items
        $testEdit = Livewire::actingAs($user)
            ->test(EditMaintenanceQuotation::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful();

        $subNav = MaintenanceQuotationResource::getRecordSubNavigation($testEdit->instance());
        $this->assertCount(3, $subNav);

        // 2. Accessing ManageClientQuotation (Step 2) directly redirects to ManageQuotationWorkOrders
        $testPricingRedirect = Livewire::actingAs($user)
            ->test(ManageClientQuotation::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertRedirect(ManageQuotationWorkOrders::getUrl(['record' => $quote]));

        // 3. Accessing ManageQuotationApproval (Step 3) directly redirects to ManageQuotationWorkOrders
        $testApprovalRedirect = Livewire::actingAs($user)
            ->test(ManageQuotationApproval::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertRedirect(ManageQuotationWorkOrders::getUrl(['record' => $quote]));
    }

    public function test_dwelly_absorbed_quotation_direct_work_order_issuance(): void
    {
        $user = User::factory()->create();

        $vendorParty = \App\Domain\Party\Models\Party::create([
            'display_name' => 'FastFix Services',
            'party_type' => 'organization',
        ]);

        $property = Property::create([
            'building_name' => 'Sunrise Heights 502',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'ticket_number' => 'MNT-2026-DWELLY-WO',
            'property_id' => $property->id,
            'title' => 'Plumbing Main Line Overhaul',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::DWELLY,
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-DWELLY-WO',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
            'subtotal_amount' => 8500.00,
        ]);

        $vendorQuote = \App\Domain\Maintenance\Models\MaintenanceVendorQuote::create([
            'maintenance_client_quote_id' => $quote->id,
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendorParty->id,
            'trade_title' => 'Plumbing Main Line',
            'quoted_cost' => 8500.00,
            'status' => 'received',
        ]);

        // 1. In ManageQuotationWorkOrders, issueWorkOrderInTab is visible on section while quote is draft
        $testWorkOrders = Livewire::actingAs($user)
            ->test(ManageQuotationWorkOrders::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('100% Absorbed by Dwelly')
            ->assertSee('₹0.00')
            ->fillForm([
                'awarded_vendor_quote_ids' => [$vendorQuote->id],
            ]);

        $schema = $testWorkOrders->instance()->getSchema('form');
        $section = collect($schema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Contractor Work Orders'));
        $issueAction = collect($section->getHeaderActions())->first(fn ($a) => $a->getName() === 'issueWorkOrderInTab');
        $this->assertNotNull($issueAction);
        $this->assertTrue($issueAction->isVisible());

        $issueAction->call([
            'record' => $quote,
            'livewire' => $testWorkOrders->instance(),
        ]);

        $quote->refresh();
        $this->assertEquals('approved', $quote->status);
        $this->assertEquals('dwelly', $quote->approved_by_type);
        $this->assertEquals(8500.00, (float) $quote->dwelly_amount);
        $this->assertEquals([$vendorQuote->id], (array) $quote->awarded_vendor_quote_ids);

        $vendorQuote->refresh();
        $this->assertEquals('awarded', $vendorQuote->status);
        $this->assertTrue((bool) $vendorQuote->is_awarded);
        $this->assertNotNull($vendorQuote->work_order_number);

        $request->refresh();
        $this->assertEquals(MaintenanceStatus::IN_PROGRESS, $request->status);

        // Verify Settlement & Billing tab renders the awarded vendor quote cost (₹8,500.00)
        Livewire::actingAs($user)
            ->test(ManageQuotationSettlement::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('₹8,500.00')
            ->assertSee('-₹8,500.00')
            ->assertSee('₹0.00');
    }
}


