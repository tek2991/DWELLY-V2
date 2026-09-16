<?php

namespace Tests\Feature;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Services\PropertyBillCreationService;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Properties\Pages\PropertyFinancials;
use App\Filament\Resources\Properties\RelationManagers\PropertyBillsRelationManager;
use App\Filament\Resources\Properties\RelationManagers\PropertyInvoicesRelationManager;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Invoice;
use Tests\TestCase;

class PropertyFinancialsBillingTabTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Property $property;
    protected Contact $vendorContact;
    protected Contact $tenantContact;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder::class);

        Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('Business Owner');
        $this->actingAs($this->user);

        $org = \Tek2991\Accounting\Models\Organization::create([
            'name' => 'Dwelly Living Private Limited',
            'legal_name' => 'Dwelly Living Private Limited',
        ]);

        $this->branch = Branch::firstOrCreate(
            ['code' => 'TEST-BR'],
            [
                'organization_id' => $org->id,
                'name' => 'Test Branch',
                'city' => 'Guwahati',
                'is_active' => true,
            ]
        );

        $this->property = Property::create([
            'code' => 'PROP-TEST-001',
            'building_name' => 'Dwelly Palm Residency',
            'address_line_1' => 'Flat 402, Tower B',
            'city' => 'Guwahati',
            'branch_id' => $this->branch->id,
            'status' => 'occupied',
        ]);

        $this->vendorContact = Contact::create([
            'name' => 'BESCOM Power Board',
            'type' => ContactType::Vendor,
            'branch_id' => $this->branch->id,
        ]);

        $this->tenantContact = Contact::create([
            'name' => 'Rajesh Sharma',
            'type' => ContactType::Customer,
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_property_financials_page_can_be_mounted_with_invoices_tab(): void
    {
        Livewire::test(PropertyFinancials::class, [
            'record' => $this->property->id,
        ])
            ->assertSuccessful()
            ->assertSee('Financial Terms & MOU')
            ->assertSee('Invoices, Bills & Receipts');
    }

    public function test_property_invoices_relation_manager_scopes_invoices_correctly(): void
    {
        // Invoice 1: Directly for this property
        $invoice1 = Invoice::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $this->tenantContact->id,
            'invoice_number' => 'INV-TEST-001',
            'reference_type' => Property::class,
            'reference_id' => $this->property->id,
            'status' => InvoiceStatus::Sent,
            'issue_date' => now()->toDateString(),
            'grand_total' => 25000,
            'amount_paid' => 10000,
            'balance_due' => 15000,
            'currency_code' => 'INR',
            'exchange_rate' => 1.0,
        ]);

        // Invoice 2: For another property
        $otherProperty = Property::create([
            'code' => 'PROP-OTHER-002',
            'building_name' => 'Other Heights',
            'status' => 'occupied',
            'branch_id' => $this->branch->id,
        ]);

        $invoiceOther = Invoice::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $this->tenantContact->id,
            'invoice_number' => 'INV-OTHER-999',
            'reference_type' => Property::class,
            'reference_id' => $otherProperty->id,
            'status' => InvoiceStatus::Sent,
            'issue_date' => now()->toDateString(),
            'grand_total' => 50000,
            'amount_paid' => 0,
            'balance_due' => 50000,
            'currency_code' => 'INR',
            'exchange_rate' => 1.0,
        ]);

        Livewire::test(PropertyInvoicesRelationManager::class, [
            'ownerRecord' => $this->property,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$invoice1])
            ->assertCanNotSeeTableRecords([$invoiceOther]);
    }

    public function test_property_bills_relation_manager_scopes_bills_correctly(): void
    {
        // Bill 1: For this property
        $bill1 = Bill::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $this->vendorContact->id,
            'bill_number' => 'BILL-TEST-001',
            'reference_type' => Property::class,
            'reference_id' => $this->property->id,
            'status' => BillStatus::Received,
            'issue_date' => now()->toDateString(),
            'grand_total' => 3500,
            'amount_paid' => 0,
            'balance_due' => 3500,
            'notes' => '[Property Utility - Electricity / Power]',
            'currency_code' => 'INR',
            'exchange_rate' => 1.0,
        ]);

        // Bill 2: For another property
        $otherProperty = Property::create([
            'code' => 'PROP-OTHER-003',
            'building_name' => 'Other Residency',
            'status' => 'occupied',
            'branch_id' => $this->branch->id,
        ]);

        $billOther = Bill::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $this->vendorContact->id,
            'bill_number' => 'BILL-OTHER-999',
            'reference_type' => Property::class,
            'reference_id' => $otherProperty->id,
            'status' => BillStatus::Received,
            'issue_date' => now()->toDateString(),
            'grand_total' => 8000,
            'amount_paid' => 0,
            'balance_due' => 8000,
            'currency_code' => 'INR',
            'exchange_rate' => 1.0,
        ]);

        Livewire::test(PropertyBillsRelationManager::class, [
            'ownerRecord' => $this->property,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([$bill1])
            ->assertCanNotSeeTableRecords([$billOther]);
    }

    public function test_property_financial_summary_calculates_correct_totals(): void
    {
        Invoice::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $this->tenantContact->id,
            'invoice_number' => 'INV-SUM-001',
            'reference_type' => Property::class,
            'reference_id' => $this->property->id,
            'status' => InvoiceStatus::PartiallyPaid,
            'issue_date' => now()->toDateString(),
            'grand_total' => 20000,
            'amount_paid' => 12000,
            'balance_due' => 8000,
            'currency_code' => 'INR',
            'exchange_rate' => 1.0,
        ]);

        Bill::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $this->vendorContact->id,
            'bill_number' => 'BILL-SUM-001',
            'reference_type' => Property::class,
            'reference_id' => $this->property->id,
            'status' => BillStatus::Received,
            'issue_date' => now()->toDateString(),
            'grand_total' => 5000,
            'amount_paid' => 2000,
            'balance_due' => 3000,
            'currency_code' => 'INR',
            'exchange_rate' => 1.0,
        ]);

        $summary = $this->property->getFinancialSummary();

        $this->assertEquals(20000.0, $summary['total_invoiced']);
        $this->assertEquals(12000.0, $summary['total_collected']);
        $this->assertEquals(8000.0, $summary['receivables_due']);
        $this->assertEquals(1, $summary['invoices_count']);

        $this->assertEquals(5000.0, $summary['total_bills']);
        $this->assertEquals(2000.0, $summary['bills_paid']);
        $this->assertEquals(3000.0, $summary['payables_due']);
        $this->assertEquals(1, $summary['bills_count']);
    }

    public function test_record_bill_header_action_creates_bill_for_property(): void
    {
        Livewire::test(PropertyBillsRelationManager::class, [
            'ownerRecord' => $this->property,
        ])
            ->callTableAction('recordBill', data: [
                'category' => 'utility_electricity',
                'contact_id' => $this->vendorContact->id,
                'vendor_reference' => 'BESCOM-SEP-2026',
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(10)->toDateString(),
                'amount' => 4500.0,
                'auto_post' => false,
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas((new Bill)->getTable(), [
            'reference_type' => Property::class,
            'reference_id' => $this->property->id,
            'contact_id' => $this->vendorContact->id,
            'vendor_reference' => 'BESCOM-SEP-2026',
        ]);
    }
}
