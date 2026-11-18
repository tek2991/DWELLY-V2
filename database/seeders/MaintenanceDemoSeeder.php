<?php

namespace Database\Seeders;

use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceClientQuoteItem;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceRequestItem;
use App\Domain\Maintenance\Models\MaintenanceVendorQuote;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use Illuminate\Database\Seeder;

class MaintenanceDemoSeeder extends Seeder
{
    public function run(): void
    {
        $properties = Property::with('rooms')->take(3)->get();
        if ($properties->isEmpty()) {
            return;
        }

        $pranab = Party::where('display_name', 'like', '%Pranab Jyoti Deka%')->first();
        $plumber = Party::where('display_name', 'like', '%Kamakhya Plumbing%')->first();
        $electrician = Party::where('display_name', 'like', '%Brahmaputra Electrical%')->first();
        $painter = Party::where('display_name', 'like', '%Rainbow Colors%')->first();

        // -------------------------------------------------------------
        // Ticket 1: Wall Seepage & Vanity Cabinet Repair
        // -------------------------------------------------------------
        $prop1 = $properties[0];
        $room1 = $prop1->rooms->first();

        $ticket1 = MaintenanceRequest::create([
            'ticket_number' => MaintenanceRequest::generateTicketNumber(),
            'property_id' => $prop1->id,
            'title' => 'Wall Seepage and Master Bathroom Woodwork Repair',
            'status' => MaintenanceStatus::SUBMITTED,
            'priority' => MaintenancePriority::HIGH,
            'payer_type' => PayerType::OWNER,
            'description' => 'Tenant reported persistent wall seepage in the hallway and moisture damage on the master bathroom vanity cabinet.',
        ]);

        $defect1 = MaintenanceRequestItem::create([
            'maintenance_request_id' => $ticket1->id,
            'itemable_type' => $room1 ? get_class($room1) : null,
            'itemable_id' => $room1?->id,
            'issue_description' => 'Wall seepage and peeling plaster',
            'repair_action' => 'Repair',
        ]);

        $defect2 = MaintenanceRequestItem::create([
            'maintenance_request_id' => $ticket1->id,
            'issue_description' => 'Damaged washbasin vanity cabinet hinges and swollen wood',
            'repair_action' => 'Replace',
        ]);

        if ($pranab) {
            $vq1 = MaintenanceVendorQuote::create([
                'maintenance_request_id' => $ticket1->id,
                'vendor_party_id' => $pranab->id,
                'trade_title' => 'Woodcraft & Carpentry',
                'quoted_cost' => 4000.00,
                'maintenance_request_item_ids' => [$defect1->id, $defect2->id],
                'status' => 'draft',
            ]);
        }

        if ($painter) {
            MaintenanceVendorQuote::create([
                'maintenance_request_id' => $ticket1->id,
                'vendor_party_id' => $painter->id,
                'trade_title' => 'Waterproof Primer & Painting',
                'quoted_cost' => 2500.00,
                'maintenance_request_item_ids' => [$defect1->id],
                'status' => 'draft',
            ]);
        }

        $quote1 = MaintenanceClientQuote::create([
            'quote_number' => MaintenanceClientQuote::generateQuoteNumber(),
            'maintenance_request_id' => $ticket1->id,
            'status' => 'draft',
            'margin_percentage' => 10.00,
            'gst_percentage' => 18.00,
        ]);

        // Pre-create 1 line item to demonstrate the coverage widget in action
        if ($pranab && isset($vq1)) {
            MaintenanceClientQuoteItem::create([
                'maintenance_client_quote_id' => $quote1->id,
                'vendor_quote_id' => $vq1->id,
                'vendor_party_id' => $pranab->id,
                'maintenance_request_item_ids' => [$defect1->id],
                'description' => 'Repair - Wall seepage and peeling plaster',
                'quantity' => 1,
                'vendor_cost' => 2000.00,
                'unit_price' => 2200.00,
                'total_price' => 2200.00,
            ]);
            $quote1->recalculateTotals();
        }

        // -------------------------------------------------------------
        // Ticket 2: Electrical & Plumbing Overhaul
        // -------------------------------------------------------------
        $prop2 = $properties->count() > 1 ? $properties[1] : $prop1;
        $ticket2 = MaintenanceRequest::create([
            'ticket_number' => MaintenanceRequest::generateTicketNumber(),
            'property_id' => $prop2->id,
            'title' => 'Geyser Sparking and Kitchen Sink Drain Blockage',
            'status' => MaintenanceStatus::SUBMITTED,
            'priority' => MaintenancePriority::HIGH,
            'payer_type' => PayerType::OWNER,
            'description' => '16A Geyser switch is sparking when turned on, and kitchen sink pipe is leaking.',
        ]);

        $defect3 = MaintenanceRequestItem::create([
            'maintenance_request_id' => $ticket2->id,
            'issue_description' => 'Geyser electrical point sparking',
            'repair_action' => 'Replace switch & wiring',
        ]);

        $defect4 = MaintenanceRequestItem::create([
            'maintenance_request_id' => $ticket2->id,
            'issue_description' => 'Kitchen sink drain leak and blockage',
            'repair_action' => 'Clean & replace P-trap',
        ]);

        if ($electrician) {
            MaintenanceVendorQuote::create([
                'maintenance_request_id' => $ticket2->id,
                'vendor_party_id' => $electrician->id,
                'trade_title' => 'Electrical Overhaul',
                'quoted_cost' => 1800.00,
                'maintenance_request_item_ids' => [$defect3->id],
                'status' => 'draft',
            ]);
        }

        if ($plumber) {
            MaintenanceVendorQuote::create([
                'maintenance_request_id' => $ticket2->id,
                'vendor_party_id' => $plumber->id,
                'trade_title' => 'Drain Line Servicing',
                'quoted_cost' => 1200.00,
                'maintenance_request_item_ids' => [$defect4->id],
                'status' => 'draft',
            ]);
        }

        MaintenanceClientQuote::create([
            'quote_number' => MaintenanceClientQuote::generateQuoteNumber(),
            'maintenance_request_id' => $ticket2->id,
            'status' => 'draft',
            'margin_percentage' => 10.00,
            'gst_percentage' => 18.00,
        ]);
    }
}
