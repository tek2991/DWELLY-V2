<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceClientQuoteItem;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceVendorQuote;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Operations\MaintenanceRequestResource\Pages\EditMaintenanceRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceQuotationSyncLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Property $property;
    protected Party $ownerParty;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['email' => 'admin@dwelly.test']);
        $this->actingAs($this->user);

        $this->property = Property::create([
            'building_name' => 'Sapphire Heights 402',
            'status' => 'active',
        ]);

        $this->ownerParty = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Amit Sharma (Owner)',
            'email' => 'amit@dwelly.test',
        ]);
    }

    public function test_client_quote_items_lifecycle_hooks_automatically_recalculate_totals(): void
    {
        $ticket = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'title' => 'Plumbing leakage in master bath',
            'status' => MaintenanceStatus::SUBMITTED,
            'priority' => MaintenancePriority::MEDIUM,
            'payer_type' => PayerType::OWNER,
        ]);

        $quote = MaintenanceClientQuote::create([
            'maintenance_request_id' => $ticket->id,
            'quote_number' => MaintenanceClientQuote::generateQuoteNumber(),
            'status' => 'draft',
            'margin_percentage' => 10.00,
            'gst_percentage' => 18.00,
            'total_amount' => 0.00,
        ]);

        $ticket->update(['current_client_quote_id' => $quote->id]);

        // Create line item 1
        $item1 = MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'description' => 'Fix pipe elbow',
            'quantity' => 1,
            'unit_price' => 1000.00,
            'vendor_cost' => 700.00,
        ]);

        $quote->refresh();
        $ticket->refresh();

        // 1000 subtotal + 18% tax = 1180
        $this->assertEquals(1000.00, (float) $quote->subtotal_amount);
        $this->assertEquals(180.00, (float) $quote->tax_amount);
        $this->assertEquals(1180.00, (float) $quote->total_amount);
        $this->assertEquals(1180.00, (float) $quote->owner_amount);

        // Ticket totals should be synchronized automatically
        $this->assertEquals(1180.00, (float) $ticket->total_client_cost);
        $this->assertEquals(1180.00, (float) $ticket->owner_amount);
        $this->assertEquals(700.00, (float) $ticket->total_vendor_cost);

        // Create line item 2
        $item2 = MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'description' => 'Replace sealant',
            'quantity' => 2,
            'unit_price' => 500.00,
            'vendor_cost' => 300.00,
        ]);

        $quote->refresh();
        $ticket->refresh();

        // Subtotal = 1000 + 1000 = 2000. Tax = 360. Total = 2360. Vendor total = 700 + 600 = 1300
        $this->assertEquals(2000.00, (float) $quote->subtotal_amount);
        $this->assertEquals(360.00, (float) $quote->tax_amount);
        $this->assertEquals(2360.00, (float) $quote->total_amount);
        $this->assertEquals(2360.00, (float) $ticket->total_client_cost);
        $this->assertEquals(1300.00, (float) $ticket->total_vendor_cost);

        // Delete line item 2
        $item2->delete();

        $quote->refresh();
        $ticket->refresh();

        // Should return back to item 1 totals
        $this->assertEquals(1000.00, (float) $quote->subtotal_amount);
        $this->assertEquals(1180.00, (float) $quote->total_amount);
        $this->assertEquals(1180.00, (float) $ticket->total_client_cost);
        $this->assertEquals(700.00, (float) $ticket->total_vendor_cost);
    }

    public function test_vendor_quote_lifecycle_hooks_automatically_sync_ticket_totals(): void
    {
        $ticket = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'title' => 'Electrical board sparking',
            'status' => MaintenanceStatus::SUBMITTED,
            'priority' => MaintenancePriority::HIGH,
            'payer_type' => PayerType::DWELLY,
        ]);

        $vendorParty = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Fast Electrics Ltd',
        ]);

        $vendorQuote = MaintenanceVendorQuote::create([
            'maintenance_request_id' => $ticket->id,
            'vendor_party_id' => $vendorParty->id,
            'trade_title' => 'Electrician',
            'quoted_cost' => 1500.00,
            'status' => 'received',
        ]);

        $ticket->refresh();
        $this->assertEquals(1500.00, (float) $ticket->total_vendor_cost);

        // Update vendor quote cost
        $vendorQuote->update(['quoted_cost' => 1800.00]);
        $ticket->refresh();
        $this->assertEquals(1800.00, (float) $ticket->total_vendor_cost);

        // Delete vendor quote
        $vendorQuote->delete();
        $ticket->refresh();
        $this->assertEquals(0.00, (float) $ticket->total_vendor_cost);
    }

    public function test_create_financial_workflow_action_uses_sequential_quote_number(): void
    {
        $ticket = MaintenanceRequest::create([
            'property_id' => $this->property->id,
            'title' => 'AC Compressor repair',
            'status' => MaintenanceStatus::SUBMITTED,
            'priority' => MaintenancePriority::HIGH,
            'payer_type' => PayerType::OWNER,
        ]);

        Livewire::test(EditMaintenanceRequest::class, ['record' => $ticket->id])
            ->callAction('createFinancialWorkflow');

        $ticket->refresh();
        $this->assertNotNull($ticket->current_client_quote_id);

        $quote = $ticket->currentClientQuote;
        $this->assertNotNull($quote);
        $year = date('Y');
        $this->assertMatchesRegularExpression("/^QTE-{$year}-\d{5}$/", $quote->quote_number);
    }
}
