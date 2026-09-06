<?php

namespace Tests\Feature\Finance;

use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Filament\Resources\Billing\Pages\ListMaintenanceBilling;
use App\Filament\Resources\Billing\Widgets\MaintenanceBillsTableWidget;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tek2991\Accounting\Database\Seeders\DefaultChartOfAccountsSeeder;
use Tek2991\Accounting\Enums\BillStatus;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Bill;
use Tek2991\Accounting\Models\Contact;
use Tek2991\Accounting\Models\Invoice;
use Tek2991\Accounting\Models\Organization;
use Tests\TestCase;

class MaintenanceBillingTabsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected MaintenanceRequest $ticket;
    protected Invoice $clientInvoice;
    protected Bill $vendorBill;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DefaultChartOfAccountsSeeder::class);

        $org = Organization::create([
            'name' => 'Dwelly Living Private Limited',
            'legal_name' => 'Dwelly Living Private Limited',
        ]);

        $this->branch = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Guwahati Branch',
            'code' => 'GHY',
            'city' => 'Guwahati',
            'is_active' => true,
        ]);

        Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);

        $this->user = User::factory()->create();
        $this->user->assignRole('Business Owner');
        $this->actingAs($this->user);

        $clientContact = Contact::create([
            'organization_id' => $org->id,
            'branch_id' => $this->branch->id,
            'name' => 'Rahul Sharma (Owner)',
            'type' => 'customer',
        ]);

        $vendorContact = Contact::create([
            'organization_id' => $org->id,
            'branch_id' => $this->branch->id,
            'name' => 'Apex Plumbing Ltd',
            'type' => 'vendor',
        ]);

        $property = \App\Domain\Property\Models\Property::create([
            'branch_id' => $this->branch->id,
            'building_name' => 'Subham Heights',
            'code' => 'PROP-SH-01',
            'status' => 'Occupied',
            'is_listed' => true,
        ]);

        $this->ticket = MaintenanceRequest::create([
            'branch_id' => $this->branch->id,
            'property_id' => $property->id,
            'ticket_number' => 'MNT-2026-9901',
            'title' => 'Emergency Pipe Burst Repair',
            'status' => 'work_completed',
        ]);

        // Client Invoice (Receivable from Owner)
        $this->clientInvoice = Invoice::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $clientContact->id,
            'invoice_number' => 'INV-MNT-9901',
            'reference_type' => MaintenanceRequest::class,
            'reference_id' => $this->ticket->id,
            'status' => InvoiceStatus::Sent,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency_code' => 'INR',
            'subtotal' => 6000,
            'grand_total' => 6000,
            'amount_paid' => 0,
            'balance_due' => 6000,
            'notes' => 'Maintenance Invoice for Ticket #MNT-2026-9901',
        ]);

        $incomeAccount = \Tek2991\Accounting\Models\Account::where('type', 'revenue')->first();
        $this->clientInvoice->items()->create([
            'description' => 'Pipe Burst Repair Service',
            'line_type' => \Tek2991\Accounting\Enums\DocumentLineType::Account,
            'income_account_id' => $incomeAccount->id,
            'quantity' => 1,
            'unit_price' => 6000,
            'subtotal' => 6000,
            'total' => 6000,
            'line_total' => 6000,
        ]);

        // Contractor Bill (Payable to Plumber)
        $payableAccount = \Tek2991\Accounting\Models\Account::where('system_role', \Tek2991\Accounting\Enums\SystemRole::TradePayable)->first();
        $expenseAccount = \Tek2991\Accounting\Models\Account::where('type', 'expense')->first();

        $vendorContact->update(['payable_account_id' => $payableAccount?->id]);

        $this->vendorBill = Bill::create([
            'branch_id' => $this->branch->id,
            'contact_id' => $vendorContact->id,
            'bill_number' => 'BILL-MNT-9901',
            'reference_type' => MaintenanceRequest::class,
            'reference_id' => $this->ticket->id,
            'vendor_reference' => 'WO-PLUMB-01',
            'status' => BillStatus::Draft,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'currency_code' => 'INR',
            'subtotal' => 4500,
            'grand_total' => 4500,
            'amount_paid' => 0,
            'balance_due' => 4500,
            'notes' => 'Vendor Bill for Plumbing Work Order #WO-PLUMB-01 (Ticket #MNT-2026-9901)',
        ]);

        $this->vendorBill->items()->create([
            'description' => 'Plumbing Repair Service',
            'line_type' => \Tek2991\Accounting\Enums\DocumentLineType::Account,
            'expense_account_id' => $expenseAccount->id,
            'quantity' => 1,
            'unit_price' => 4500,
            'subtotal' => 4500,
            'total' => 4500,
        ]);

        app(\Tek2991\Accounting\Services\BillService::class)->recalculateTotals($this->vendorBill);
    }

    public function test_maintenance_billing_page_mounts_with_dual_tabs(): void
    {
        Livewire::test(ListMaintenanceBilling::class)
            ->assertSuccessful()
            ->assertSee('Client Invoices (Receivable)')
            ->assertSee('Contractor Bills (Payable)')
            ->assertSee('INV-MNT-9901')
            ->assertSee('Rahul Sharma (Owner)');
    }

    public function test_contractor_bills_widget_displays_maintenance_bills(): void
    {
        Livewire::test(MaintenanceBillsTableWidget::class)
            ->assertSuccessful()
            ->assertSee('BILL-MNT-9901')
            ->assertSee('Apex Plumbing Ltd')
            ->assertSee('MNT-2026-9901')
            ->assertSee('₹4,500.00');
    }

    public function test_contractor_bill_can_be_approved_and_posted(): void
    {
        Livewire::test(MaintenanceBillsTableWidget::class)
            ->callTableAction('post_bill', $this->vendorBill)
            ->assertHasNoTableActionErrors();

        $this->assertEquals(BillStatus::Received, $this->vendorBill->fresh()->status);
    }

    public function test_client_invoice_can_be_approved_and_posted(): void
    {
        $this->clientInvoice->update(['status' => InvoiceStatus::Draft]);

        Livewire::test(ListMaintenanceBilling::class)
            ->callTableAction('post_invoice', $this->clientInvoice)
            ->assertHasNoTableActionErrors();

        $this->assertEquals(InvoiceStatus::Sent, $this->clientInvoice->fresh()->status);
        $this->assertNotNull($this->clientInvoice->fresh()->transaction_id);
    }

    public function test_client_invoice_can_record_payment(): void
    {
        $bankAccount = \Tek2991\Accounting\Models\Account::where('type', 'asset')
            ->where('system_role', \Tek2991\Accounting\Enums\SystemRole::Bank)
            ->first();

        Livewire::test(ListMaintenanceBilling::class)
            ->callTableAction('record_payment', $this->clientInvoice, data: [
                'amount' => 2000,
                'payment_account_id' => $bankAccount->id,
                'payment_date' => now()->toDateString(),
                'reference' => 'UTR12345678',
                'notes' => 'Client Bank Transfer',
            ])
            ->assertHasNoTableActionErrors();

        $fresh = $this->clientInvoice->fresh();
        $this->assertEquals(InvoiceStatus::PartiallyPaid, $fresh->status);
        $this->assertEquals(2000, (float) $fresh->amount_paid);
        $this->assertEquals(4000, (float) $fresh->balance_due);
    }

    public function test_contractor_bill_can_record_payment(): void
    {
        app(\Tek2991\Accounting\Services\BillService::class)->post($this->vendorBill);

        $bankAccount = \Tek2991\Accounting\Models\Account::where('type', 'asset')
            ->where('system_role', \Tek2991\Accounting\Enums\SystemRole::Bank)
            ->first();

        Livewire::test(MaintenanceBillsTableWidget::class)
            ->callTableAction('record_payment', $this->vendorBill, data: [
                'amount' => 1500,
                'payment_account_id' => $bankAccount->id,
                'payment_date' => now()->toDateString(),
                'reference' => 'CHQ-987654',
                'notes' => 'Part payment to plumber',
            ])
            ->assertHasNoTableActionErrors();

        $fresh = $this->vendorBill->fresh();
        $this->assertEquals(BillStatus::PartiallyPaid, $fresh->status);
        $this->assertEquals(1500, (float) $fresh->amount_paid);
        $this->assertEquals(3000, (float) $fresh->balance_due);
    }

    public function test_bank_and_cash_options_highlight_and_auto_select_default_bank_account(): void
    {
        $defaultBankId = \Tek2991\Accounting\Facades\Accounting::getDefaultBankAccountId();
        $this->assertNotNull($defaultBankId);

        $options = \Tek2991\Accounting\Models\Account::bankAndCashOptionsWithDefault();
        $this->assertArrayHasKey($defaultBankId, $options);
        $this->assertStringContainsString('Default', $options[$defaultBankId]);
        $this->assertStringContainsString('font-weight: 600', $options[$defaultBankId]);

        // Verify that default bank is ordered first in options
        $keys = array_keys($options);
        $this->assertEquals($defaultBankId, $keys[0]);
    }
}
