<?php

namespace Tests\Feature\Finance;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Auth\Services\PermissionCatalog;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Billing\BillsResource;
use App\Filament\Resources\Billing\InvoicesResource;
use App\Filament\Resources\Billing\Pages\ListBills;
use App\Filament\Resources\Billing\Pages\ListInvoices;
use App\Models\Branch;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class InvoicesAndBillsResourcesTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $accountant;
    protected User $opsManager;
    protected User $executive;
    protected Branch $branch;
    protected Property $property;
    protected Contact $tenantContact;
    protected Contact $vendorContact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DefaultChartOfAccountsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $org = Organization::create([
            'name' => 'Dwelly Living Private Limited',
            'legal_name' => 'Dwelly Living Private Limited',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Bangalore Central',
            'code' => 'BLR-01',
            'city' => 'Bangalore',
            'is_active' => true,
        ]);

        $this->owner = User::factory()->create();
        $this->owner->assignRole(RoleName::BUSINESS_OWNER->value);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole(RoleName::ACCOUNTANT->value);

        $this->opsManager = User::factory()->create();
        $this->opsManager->assignRole(RoleName::OPERATIONS_MANAGER->value);

        $this->executive = User::factory()->create();
        $this->executive->assignRole(RoleName::OPERATIONS_EXECUTIVE->value);

        $this->tenantContact = Contact::create([
            'organization_id' => $org->id,
            'branch_id' => $this->branch->id,
            'name' => 'Kunal Roy (Tenant)',
            'type' => 'customer',
        ]);

        $this->vendorContact = Contact::create([
            'organization_id' => $org->id,
            'branch_id' => $this->branch->id,
            'name' => 'Prime Electricals',
            'type' => 'vendor',
        ]);

        $this->property = Property::create([
            'branch_id' => $this->branch->id,
            'building_name' => 'Sobha Dream Acres',
            'address_line_1' => 'Flat 1102, Wing B',
            'code' => 'PROP-SDA-1102',
            'status' => 'Occupied',
            'is_listed' => true,
        ]);
    }

    public function test_invoices_resource_displays_all_types_of_invoices_and_filters_tabs(): void
    {
        $this->actingAs($this->owner);

        $agreement = TenancyAgreement::create([
            'property_id' => $this->property->id,
            'code' => 'TNC-2026-0001',
            'status' => 'active',
            'rent_amount' => 35000,
            'security_deposit' => 70000,
        ]);

        $ticket = MaintenanceRequest::create([
            'branch_id' => $this->branch->id,
            'property_id' => $this->property->id,
            'ticket_number' => 'TKT-2026-101',
            'title' => 'Geyser Thermostat Replacement',
            'status' => 'work_completed',
        ]);

        // 1. Rent Demand Invoice
        $rentInvoice = Invoice::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $this->tenantContact->id,
            'invoice_number' => 'INV-RENT-001',
            'reference_type' => TenancyAgreement::class,
            'reference_id' => $agreement->id,
            'status' => InvoiceStatus::Sent,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'subtotal' => 35000,
            'grand_total' => 35000,
            'amount_paid' => 0,
            'balance_due' => 35000,
            'notes' => 'Rent demand for current cycle',
        ]);

        // 2. Maintenance Charge Invoice
        $maintInvoice = Invoice::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $this->tenantContact->id,
            'invoice_number' => 'INV-MNT-101',
            'reference_type' => MaintenanceRequest::class,
            'reference_id' => $ticket->id,
            'status' => InvoiceStatus::Sent,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal' => 2500,
            'grand_total' => 2500,
            'amount_paid' => 0,
            'balance_due' => 2500,
            'notes' => 'Maintenance repair charge',
        ]);

        // 3. Documentation Fee Invoice
        $docInvoice = Invoice::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $this->tenantContact->id,
            'invoice_number' => 'INV-DOC-001',
            'status' => InvoiceStatus::Paid,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->toDateString(),
            'subtotal' => 1500,
            'grand_total' => 1500,
            'amount_paid' => 1500,
            'balance_due' => 0,
            'document_snapshot' => ['invoice_category' => 'documentation_charge'],
            'notes' => 'Lease documentation charge',
        ]);

        Livewire::test(ListInvoices::class)
            ->assertSuccessful()
            ->assertSee('INV-RENT-001')
            ->assertSee('INV-MNT-101')
            ->assertSee('INV-DOC-001')
            ->assertSee('Rent Demand')
            ->assertSee('Maintenance Charge')
            ->assertSee('Documentation Fee')
            ->assertSee('Sobha Dream Acres');

        // Test filtering by Rent tab
        Livewire::test(ListInvoices::class)
            ->set('activeTab', 'rent')
            ->assertSee('INV-RENT-001')
            ->assertDontSee('INV-MNT-101')
            ->assertDontSee('INV-DOC-001');

        // Test filtering by Maintenance tab
        Livewire::test(ListInvoices::class)
            ->set('activeTab', 'maintenance')
            ->assertSee('INV-MNT-101')
            ->assertDontSee('INV-RENT-001');
    }

    public function test_bills_resource_displays_all_types_of_bills_and_filters_tabs(): void
    {
        $this->actingAs($this->owner);

        $ticket = MaintenanceRequest::create([
            'branch_id' => $this->branch->id,
            'property_id' => $this->property->id,
            'ticket_number' => 'TKT-2026-202',
            'title' => 'Main Switchboard Rewiring',
            'status' => 'work_completed',
        ]);

        // 1. Maintenance Work Order Bill
        $maintBill = Bill::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $this->vendorContact->id,
            'bill_number' => 'BILL-MNT-202',
            'vendor_reference' => 'PE-INV-99',
            'reference_type' => MaintenanceRequest::class,
            'reference_id' => $ticket->id,
            'status' => BillStatus::Received,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'subtotal' => 4500,
            'grand_total' => 4500,
            'amount_paid' => 0,
            'balance_due' => 4500,
            'notes' => 'Work order electrical repair',
        ]);

        // 2. Utility Bill
        $utilityBill = Bill::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $this->vendorContact->id,
            'bill_number' => 'BILL-UTIL-001',
            'vendor_reference' => 'BESCOM-AUG-26',
            'reference_type' => Property::class,
            'reference_id' => $this->property->id,
            'status' => BillStatus::Received,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(10)->toDateString(),
            'subtotal' => 3200,
            'grand_total' => 3200,
            'amount_paid' => 0,
            'balance_due' => 3200,
            'notes' => 'Electricity power bill for common area',
        ]);

        Livewire::test(ListBills::class)
            ->assertSuccessful()
            ->assertSee('BILL-MNT-202')
            ->assertSee('BILL-UTIL-001')
            ->assertSee('Work Order / Repair')
            ->assertSee('Property Utility')
            ->assertSee('Prime Electricals')
            ->assertSee('Sobha Dream Acres');

        // Test filtering by Work Orders tab
        Livewire::test(ListBills::class)
            ->set('activeTab', 'maintenance')
            ->assertSee('BILL-MNT-202')
            ->assertDontSee('BILL-UTIL-001');

        // Test filtering by Utilities tab
        Livewire::test(ListBills::class)
            ->set('activeTab', 'utilities')
            ->assertSee('BILL-UTIL-001')
            ->assertDontSee('BILL-MNT-202');
    }

    public function test_role_based_access_control_on_invoices_and_bills(): void
    {
        // 1. Business Owner has full access
        $this->actingAs($this->owner);
        $this->assertTrue(InvoicesResource::canViewAny());
        $this->assertTrue(BillsResource::canViewAny());
        $this->assertTrue(InvoicesResource::canCreate());
        $this->assertTrue(BillsResource::canCreate());

        // 2. Accountant has full financial access
        $this->actingAs($this->accountant);
        $this->assertTrue(InvoicesResource::canViewAny());
        $this->assertTrue(BillsResource::canViewAny());
        $this->assertTrue(InvoicesResource::canCreate());
        $this->assertTrue(BillsResource::canCreate());
        $this->assertTrue($this->accountant->can('billing.bill.pay'));
        $this->assertTrue($this->accountant->can('billing.invoice.post'));

        // 3. Operations Manager can view and log bills/invoices, but cannot disburse payments
        $this->actingAs($this->opsManager);
        $this->assertTrue(InvoicesResource::canViewAny());
        $this->assertTrue(BillsResource::canViewAny());
        $this->assertTrue(InvoicesResource::canCreate());
        $this->assertTrue(BillsResource::canCreate());
        $this->assertTrue($this->opsManager->can('billing.bill.approve'));
        $this->assertFalse($this->opsManager->can('billing.bill.pay'));

        // 4. Operations Executive (Field staff) is strictly blocked
        $this->actingAs($this->executive);
        $this->assertFalse(InvoicesResource::canViewAny());
        $this->assertFalse(BillsResource::canViewAny());
        $this->assertFalse(InvoicesResource::canCreate());
        $this->assertFalse(BillsResource::canCreate());
    }
}
