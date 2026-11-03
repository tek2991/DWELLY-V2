<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceClientQuoteItem;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceRequestItem;
use App\Domain\Maintenance\Models\MaintenanceVendorQuote;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyRoom;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageClientQuotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceQuotationLineItemsOverhaulTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Property $property;
    protected Party $vendorA;
    protected Party $vendorB;
    protected MaintenanceRequest $ticket;
    protected MaintenanceRequestItem $defect1;
    protected MaintenanceRequestItem $defect2;
    protected MaintenanceClientQuote $quote;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->property = Property::create([
            'building_name' => 'Emerald Towers 504',
            'status' => 'active',
        ]);

        $roomType = \App\Domain\Property\Models\RoomType::create(['name' => 'Bathroom', 'slug' => 'bathroom-' . uniqid()]);
        $roomDef = \App\Domain\Property\Models\RoomDefinition::create([
            'room_type_id' => $roomType->id,
            'name' => 'Master Bathroom',
            'slug' => 'master-bathroom-' . uniqid(),
        ]);

        $room = PropertyRoom::create([
            'property_id' => $this->property->id,
            'room_definition_id' => $roomDef->id,
            'custom_name' => 'Master Bathroom',
        ]);

        $trade = \App\Domain\Party\Models\VendorTrade::create(['name' => 'General Maintenance', 'is_active' => true]);

        $this->vendorA = Party::create([
            'party_type' => 'organization',
            'display_name' => 'Apex Plumbing & Repairs',
            'email' => 'apex@repairs.test',
        ]);
        \App\Domain\Party\Models\VendorProfile::create([
            'party_id' => $this->vendorA->id,
            'vendor_trade_id' => $trade->id,
            'onboarding_status' => \App\Domain\Party\Enums\VendorOnboardingStatus::VERIFIED->value,
        ]);

        $this->vendorB = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Ramesh Kumar (Electrician)',
            'email' => 'ramesh@electric.test',
        ]);
        \App\Domain\Party\Models\VendorProfile::create([
            'party_id' => $this->vendorB->id,
            'vendor_trade_id' => $trade->id,
            'onboarding_status' => \App\Domain\Party\Enums\VendorOnboardingStatus::VERIFIED->value,
        ]);

        $this->ticket = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'title' => 'Plumbing and Electrical Issues',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
        ]);

        $this->defect1 = MaintenanceRequestItem::create([
            'maintenance_request_id' => $this->ticket->id,
            'itemable_type' => PropertyRoom::class,
            'itemable_id' => $room->id,
            'issue_description' => 'Leaking washbasin pipe',
            'repair_action' => 'Replace pipe connector',
        ]);

        $this->defect2 = MaintenanceRequestItem::create([
            'maintenance_request_id' => $this->ticket->id,
            'itemable_type' => PropertyRoom::class,
            'itemable_id' => $room->id,
            'issue_description' => 'Geyser switch spark',
            'repair_action' => 'Install 16A modular switch',
        ]);

        $this->quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-TEST-OVERHAUL',
            'maintenance_request_id' => $this->ticket->id,
            'status' => 'draft',
            'margin_percentage' => 10.00,
            'gst_percentage' => 18.00,
        ]);
    }

    public function test_defect_items_coverage_status_renders_unaccounted_defects_initially(): void
    {
        $test = Livewire::test(ManageClientQuotation::class, ['record' => $this->quote->getRouteKey()]);

        $test->assertSee('Repair Defect Coverage Status');
        $test->assertSee('0 of 2 Defect Items Accounted For (0%)');
        $test->assertSee('Leaking washbasin pipe');
        $test->assertSee('Geyser switch spark');
        $test->assertSee('⚠️ Unaccounted');
    }

    public function test_defect_items_coverage_status_marks_defect_accounted_for_when_line_item_targets_it(): void
    {
        MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $this->quote->id,
            'maintenance_request_item_ids' => [$this->defect1->id],
            'description' => 'Replace Washbasin Connector Pipe',
            'quantity' => 1,
            'vendor_cost' => 1500.00,
            'unit_price' => 1650.00,
            'total_price' => 1650.00,
        ]);

        $test = Livewire::test(ManageClientQuotation::class, ['record' => $this->quote->getRouteKey()]);

        $test->assertSee('1 of 2 Defect Items Accounted For (50%)');
        $test->assertSee('✓ Accounted For');
        $test->assertSee('⚠️ Unaccounted');
    }

    public function test_defect_items_coverage_status_shows_all_accounted_for_when_all_defects_covered(): void
    {
        MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $this->quote->id,
            'maintenance_request_item_ids' => [$this->defect1->id, $this->defect2->id],
            'description' => 'Complete Bathroom Plumbing & Electrical Package',
            'quantity' => 1,
            'vendor_cost' => 4000.00,
            'unit_price' => 4400.00,
            'total_price' => 4400.00,
        ]);

        $test = Livewire::test(ManageClientQuotation::class, ['record' => $this->quote->getRouteKey()]);

        $test->assertSee('All 2 Defect Items Accounted For (100%)');
        $test->assertDontSee('⚠️ Unaccounted');
    }

    public function test_sync_vendor_costs_action_updates_vendor_costs_and_vendor_links_from_tab_1(): void
    {
        $vQuote = MaintenanceVendorQuote::create([
            'maintenance_request_id' => $this->ticket->id,
            'vendor_party_id' => $this->vendorA->id,
            'trade_title' => 'Plumbing Works',
            'quoted_cost' => 2000.00,
        ]);

        $lineItem = MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $this->quote->id,
            'vendor_quote_id' => $vQuote->id,
            'maintenance_request_item_ids' => [$this->defect1->id],
            'description' => 'Plumbing Repair',
            'quantity' => 1,
            'vendor_cost' => 2000.00,
            'unit_price' => 2200.00,
            'total_price' => 2200.00,
        ]);

        // Contractor raises bid to 2500 in Tab 1
        $vQuote->update(['quoted_cost' => 2500.00]);

        $test = Livewire::test(ManageClientQuotation::class, ['record' => $this->quote->getRouteKey()]);
        $schema = $test->instance()->getSchema('form');
        $section = collect($schema->getComponents())->first(fn ($c) => $c instanceof \Filament\Schemas\Components\Section && str_contains($c->getHeading(), 'Itemized Line Items'));
        $action = collect($section->getHeaderActions())->first(fn ($a) => $a->getName() === 'syncVendorCosts');

        $this->assertNotNull($action);
        $action->call();

        $lineItem->refresh();
        $this->assertEquals(2500.00, (float) $lineItem->vendor_cost);
        $this->assertEquals(2750.00, (float) $lineItem->unit_price);
        $this->assertEquals($this->vendorA->id, $lineItem->vendor_party_id);
    }

    public function test_line_item_model_automatically_syncs_vendor_party_id_from_vendor_quote(): void
    {
        $quoteA = MaintenanceVendorQuote::create([
            'maintenance_request_id' => $this->ticket->id,
            'vendor_party_id' => $this->vendorB->id,
            'trade_title' => 'Electrical Repair',
            'quoted_cost' => 1200.00,
        ]);

        $item = MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $this->quote->id,
            'vendor_quote_id' => $quoteA->id,
            'description' => 'Fix Switch',
            'quantity' => 1,
            'vendor_cost' => 1200.00,
            'unit_price' => 1320.00,
        ]);

        $this->assertEquals($this->vendorB->id, $item->vendor_party_id);
        $this->assertEquals($this->vendorB->display_name, $item->vendor?->display_name);
    }
}
