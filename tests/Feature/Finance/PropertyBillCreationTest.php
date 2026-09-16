<?php

namespace Tests\Feature\Finance;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Finance\Services\BillingPropertyResolver;
use App\Domain\Finance\Services\PropertyBillCreationService;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Billing\Pages\ListBills;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Enums\AccountType;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Enums\ContactType;
use Tek2991\Accounting\Enums\SystemRole;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class PropertyBillCreationTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $accountant;
    protected User $opsManager;
    protected User $executive;
    protected Branch $branch;
    protected Property $property;
    protected Contact $utilityVendor;
    protected Contact $societyVendor;
    protected Contact $turnoverVendor;

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
        $this->owner->branches()->attach($this->branch);

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole(RoleName::ACCOUNTANT->value);
        $this->accountant->branches()->attach($this->branch);

        $this->opsManager = User::factory()->create();
        $this->opsManager->assignRole(RoleName::OPERATIONS_MANAGER->value);
        $this->opsManager->branches()->attach($this->branch);

        $this->executive = User::factory()->create();
        $this->executive->assignRole(RoleName::OPERATIONS_EXECUTIVE->value);
        $this->executive->branches()->attach($this->branch);

        app(\Tek2991\Accounting\Services\BranchContext::class)->set($this->branch);

        $this->property = Property::create([
            'branch_id' => $this->branch->id,
            'building_name' => 'Prestige Falcon City',
            'address_line_1' => 'Flat 802, Tower 4',
            'code' => 'PFC-802',
            'status' => 'Occupied',
            'is_listed' => true,
        ]);

        $this->utilityVendor = Contact::create([
            'organization_id' => $org->id,
            'branch_id' => $this->branch->id,
            'name' => 'BESCOM Power Utility',
            'type' => ContactType::Vendor,
        ]);

        $this->societyVendor = Contact::create([
            'organization_id' => $org->id,
            'branch_id' => $this->branch->id,
            'name' => 'Prestige Falcon City Apartment Owners Association',
            'type' => ContactType::Vendor,
        ]);

        $this->turnoverVendor = Contact::create([
            'organization_id' => $org->id,
            'branch_id' => $this->branch->id,
            'name' => 'CleanPro Facilities & Deep Cleaning',
            'type' => ContactType::Vendor,
        ]);
    }

    public function test_can_record_utility_electricity_bill_with_proper_accounting_line_items(): void
    {
        $service = app(PropertyBillCreationService::class);

        $bill = $service->createBill([
            'category' => 'utility_electricity',
            'property_id' => $this->property->id,
            'contact_id' => $this->utilityVendor->id,
            'vendor_reference' => 'BESCOM-AUG-2026',
            'issue_date' => '2026-08-10',
            'due_date' => '2026-08-24',
            'amount' => 4580.00,
            'notes' => 'Meter Consumer ID #8819283 for Aug 2026 billing cycle',
            'auto_post' => false,
        ], $this->accountant);

        $this->assertInstanceOf(Bill::class, $bill);
        $this->assertEquals(BillStatus::Draft, $bill->status);
        $this->assertEquals(4580.00, $bill->grand_total);
        $this->assertEquals(4580.00, $bill->balance_due);
        $this->assertEquals(Property::class, $bill->reference_type);
        $this->assertEquals($this->property->id, $bill->reference_id);

        // Verify line items for double-entry GL
        $this->assertCount(1, $bill->items);
        $item = $bill->items->first();
        $this->assertEquals(4580.00, $item->line_total);
        $this->assertNotNull($item->expense_account_id);

        // Verify PMS categorization
        $categoryInfo = BillingPropertyResolver::categorizeBill($bill);
        $this->assertEquals('utility', $categoryInfo['key']);
        $this->assertEquals('Property Utility', $categoryInfo['label']);

        // Verify property resolver
        $propertyLabel = BillingPropertyResolver::resolvePropertyLabel($bill);
        $this->assertStringContainsString('Prestige Falcon City', $propertyLabel);
    }

    public function test_can_record_and_auto_post_society_maintenance_bill_to_general_ledger(): void
    {
        $service = app(PropertyBillCreationService::class);

        $bill = $service->createBill([
            'category' => 'society_maintenance',
            'property_id' => $this->property->id,
            'contact_id' => $this->societyVendor->id,
            'vendor_reference' => 'HOA-Q3-2026',
            'issue_date' => '2026-07-01',
            'due_date' => '2026-07-15',
            'amount' => 12500.00,
            'notes' => 'Q3 2026 Society Maintenance Dues & Sinking Fund',
            'auto_post' => true,
        ], $this->accountant);

        $this->assertEquals(BillStatus::Received, $bill->status);
        $this->assertNotNull($bill->transaction_id);
        $this->assertEquals(12500.00, $bill->grand_total);

        // Verify General Ledger transaction
        $transaction = $bill->transaction;
        $this->assertNotNull($transaction);

        // Verify journal entries
        $creditEntry = $transaction->journalEntries()->where('type', \Tek2991\Accounting\Enums\JournalEntryType::Credit)->first();
        $this->assertNotNull($creditEntry);
        $this->assertEquals(12500.00, $creditEntry->amount);

        $debitEntry = $transaction->journalEntries()->where('type', \Tek2991\Accounting\Enums\JournalEntryType::Debit)->first();
        $this->assertNotNull($debitEntry);
        $this->assertEquals(12500.00, $debitEntry->amount);

        // Verify vendor payable balance incremented
        $this->utilityVendor->refresh();
        $this->societyVendor->refresh();
        $this->assertEquals(12500.00, $this->societyVendor->payable_balance);
    }

    public function test_can_record_turnover_deep_cleaning_bill_and_filters_under_turnover_tab(): void
    {
        $service = app(PropertyBillCreationService::class);

        $bill = $service->createBill([
            'category' => 'turnover_cleaning',
            'property_id' => $this->property->id,
            'contact_id' => $this->turnoverVendor->id,
            'vendor_reference' => 'CLN-892',
            'issue_date' => '2026-08-15',
            'due_date' => '2026-08-20',
            'amount' => 3800.00,
            'notes' => 'Post-tenancy deep scrubbing, kitchen degreasing, and balcony wash',
            'auto_post' => false,
        ], $this->opsManager);

        $categoryInfo = BillingPropertyResolver::categorizeBill($bill);
        $this->assertEquals('turnover', $categoryInfo['key']);
        $this->assertEquals('Turnover Service', $categoryInfo['label']);

        // Verify ListBills tab query matches
        $this->actingAs($this->accountant);
        Livewire::test(ListBills::class)
            ->set('activeTab', 'turnover')
            ->assertSee($bill->bill_number)
            ->assertSee('CleanPro Facilities');
    }

    public function test_record_bill_modal_action_executes_successfully_via_livewire(): void
    {
        $this->actingAs($this->accountant);

        Livewire::test(ListBills::class)
            ->assertActionExists('recordBill')
            ->callAction('recordBill', [
                'category' => 'utility_water',
                'property_id' => $this->property->id,
                'contact_id' => $this->utilityVendor->id,
                'vendor_reference' => 'BWSSB-JUL-26',
                'amount' => 1850.00,
                'issue_date' => '2026-08-01',
                'due_date' => '2026-08-15',
                'notes' => 'Water consumption bill',
                'auto_post' => false,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('acc_bills', [
            'vendor_reference' => 'BWSSB-JUL-26',
            'reference_type' => Property::class,
            'reference_id' => $this->property->id,
            'contact_id' => $this->utilityVendor->id,
        ]);
    }

    public function test_unauthorized_operations_executive_cannot_view_or_record_bills(): void
    {
        $this->actingAs($this->executive);

        $this->assertFalse(\App\Filament\Resources\Billing\BillsResource::canViewAny());
        $this->assertFalse(\App\Filament\Resources\Billing\BillsResource::canCreate());

        Livewire::test(ListBills::class)
            ->assertForbidden();
    }
}
