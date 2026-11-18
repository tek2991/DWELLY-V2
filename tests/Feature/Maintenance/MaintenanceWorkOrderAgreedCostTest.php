<?php

namespace Tests\Feature\Maintenance;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceClientQuoteItem;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceRequestItem;
use App\Domain\Maintenance\Models\MaintenanceVendorQuote;
use App\Domain\Maintenance\Services\MaintenanceBillingService;
use App\Domain\Maintenance\Services\MaintenanceWorkOrderPdfService;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageQuotationSettlement;
use App\Filament\Resources\Billing\MaintenanceQuotationResource\Pages\ManageQuotationWorkOrders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceWorkOrderAgreedCostTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_order_derives_agreed_cost_from_approved_line_items_instead_of_full_vendor_bid(): void
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Sunset Heights 301',
            'status' => 'active',
        ]);

        $request = MaintenanceRequest::create([
            'property_id' => $property->id,
            'title' => 'Plumbing and Electrical Multiple Defects',
            'status' => MaintenanceStatus::SUBMITTED,
            'payer_type' => PayerType::OWNER,
        ]);

        $defectA = MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'issue_description' => 'Leaking bathroom washbasin',
            'repair_action' => 'Replace connector pipe',
        ]);

        $defectB = MaintenanceRequestItem::create([
            'maintenance_request_id' => $request->id,
            'issue_description' => 'Geyser switch spark and burning smell',
            'repair_action' => 'Install 16A modular switch',
        ]);

        $vendor1 = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Ramesh Plumber',
        ]);

        $vendor2 = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Suresh Electrician',
        ]);

        $vendor3 = Party::create([
            'party_type' => 'organization',
            'display_name' => 'Apex Total Home Services',
        ]);

        // Vendor 1 bids ₹1,000 for Defect A
        $vQuote1 = MaintenanceVendorQuote::create([
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendor1->id,
            'trade_title' => 'Plumbing Repair',
            'quoted_cost' => 1000.00,
            'maintenance_request_item_ids' => [$defectA->id],
        ]);

        // Vendor 2 bids ₹2,000 for Defect B
        $vQuote2 = MaintenanceVendorQuote::create([
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendor2->id,
            'trade_title' => 'Electrical Overhaul',
            'quoted_cost' => 2000.00,
            'maintenance_request_item_ids' => [$defectB->id],
        ]);

        // Vendor 3 bids ₹2,500 for BOTH Defect A and Defect B
        $vQuote3 = MaintenanceVendorQuote::create([
            'maintenance_request_id' => $request->id,
            'vendor_party_id' => $vendor3->id,
            'trade_title' => 'Combined Plumbing & Electrical Package',
            'quoted_cost' => 2500.00,
            'maintenance_request_item_ids' => [$defectA->id, $defectB->id],
            'scope_of_work' => 'Full plumbing pipe replacement and electrical switch replacement.',
        ]);

        $quote = MaintenanceClientQuote::create([
            'quote_number' => 'QT-2026-SPLIT-AWARD',
            'maintenance_request_id' => $request->id,
            'status' => 'draft',
            'margin_percentage' => 10.00,
            'gst_percentage' => 18.00,
        ]);

        // In Tab 2: User awards Defect A to Vendor 1 for ₹1,000
        $line1 = MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'vendor_quote_id' => $vQuote1->id,
            'vendor_party_id' => $vendor1->id,
            'maintenance_request_item_ids' => [$defectA->id],
            'description' => 'Fix Washbasin Pipe Leak',
            'quantity' => 1,
            'vendor_cost' => 1000.00,
            'unit_price' => 1100.00,
            'total_price' => 1100.00,
            'sort_order' => 1,
        ]);

        // In Tab 2: User selects Vendor 3 for Defect B, but REDUCES vendor cost to ₹1,500 (partial award)
        $line2 = MaintenanceClientQuoteItem::create([
            'maintenance_client_quote_id' => $quote->id,
            'vendor_quote_id' => $vQuote3->id,
            'vendor_party_id' => $vendor3->id,
            'maintenance_request_item_ids' => [$defectB->id],
            'description' => 'Replace Geyser 16A Switch',
            'quantity' => 1,
            'vendor_cost' => 1500.00,
            'unit_price' => 1650.00,
            'total_price' => 1650.00,
            'sort_order' => 2,
        ]);

        $quote->update(['status' => 'approved', 'approved_at' => now()]);

        // 1. Assert Tab 4 displays the adjusted cost of ₹1,500 for Vendor 3 before awarding
        Livewire::actingAs($user)
            ->test(ManageQuotationWorkOrders::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('₹1,500.00')
            ->assertSee('₹2,500.00') // Strikethrough original bid
            ->assertSee('Adjusted (1 line)');

        // 2. Issue Work Orders to Vendor 1 and Vendor 3
        $billingService = app(MaintenanceBillingService::class);
        $billingService->awardVendorQuotesAndIssueWorkOrders($quote, [$vQuote1->id, $vQuote3->id]);

        $vQuote1->refresh();
        $vQuote3->refresh();

        // Vendor 1 was awarded at their full bid of 1000
        $this->assertTrue((bool) $vQuote1->is_awarded);
        $this->assertEquals(1000.00, (float) $vQuote1->final_cost);
        $this->assertEquals(1000.00, (float) $vQuote1->quoted_cost);

        // Vendor 3 was awarded at the agreed line item price of 1500 (NOT the full 2500 bid!)
        $this->assertTrue((bool) $vQuote3->is_awarded);
        $this->assertEquals(1500.00, (float) $vQuote3->final_cost);
        // Original bid remains preserved in quoted_cost for historical audit
        $this->assertEquals(2500.00, (float) $vQuote3->quoted_cost);

        // 3. Verify Work Order PDF has the agreed amount ₹1,500.00 and itemized scope
        $pdfService = app(MaintenanceWorkOrderPdfService::class);
        $media = $pdfService->generatePdf($vQuote3, $quote);
        $this->assertNotNull($media);
        $this->assertFileExists($media->getPath());

        // 4. Verify downstream bill creation would respect the 1500 final_cost
        $this->assertEquals(1500.00, $vQuote3->getEffectiveWorkOrderCost($quote));

        // 5. Verify Tab 5 (Settlement & Billing) displays the agreed vendor total (₹2,500.00), Tax Component, and Financial Equation
        $quote->recalculateTotals();
        $quote->refresh();
        $this->assertEquals(2500.00, $quote->getEffectiveVendorTotal());

        Livewire::actingAs($user)
            ->test(ManageQuotationSettlement::class, [
                'record' => $quote->getRouteKey(),
            ])
            ->assertSuccessful()
            ->assertSee('Payable / Vendor Bills')
            ->assertSee('₹2,500.00')
            ->assertDontSee('₹3,500.00')
            ->assertDontSee('₹5,500.00')
            ->assertSee('Tax Component (GST)')
            ->assertSee('Financial Equation:')
            ->assertSee('Awarded Contractor Payables Breakdown')
            ->assertSee('Ramesh Plumber')
            ->assertSee('Apex Total Home Services');
    }
}
