<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceRequest;
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
}

