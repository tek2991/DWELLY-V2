<?php

namespace Tests\Feature\Authorization;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Pages\Billing\BulkGenerateMonthlyRent;
use App\Filament\Pages\Billing\BulkGenerateOwnerPayouts;
use App\Filament\Pages\Billing\FinancialDashboard;
use App\Filament\Pages\Billing\FinancialOperationsHub;
use App\Filament\Resources\Billing\RentDemandsResource;
use App\Filament\Resources\OwnerPayouts\OwnerPayoutResource;
use App\Models\Branch;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tek2991\Accounting\Models\Account;
use Tek2991\Accounting\Models\BankAccount;
use Tek2991\Accounting\Models\Organization;
use Tek2991\Accounting\Models\Transaction;
use Tests\TestCase;

class BillingAndAccountingRbacTest extends TestCase
{
    use RefreshDatabase;

    protected User $owner;

    protected User $cityManager;

    protected User $supplyManager;

    protected User $demandManager;

    protected User $opsManager;

    protected User $opsExecutive;

    protected User $accountant;

    protected Property $property;

    protected Party $ownerParty;

    protected Party $tenantParty;

    protected TenancyAgreement $agreement;

    protected OwnerPayout $draftPayout;

    protected OwnerPayout $completedPayout;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->owner = User::factory()->create();
        $this->owner->assignRole('Business Owner');

        $this->cityManager = User::factory()->create();
        $this->cityManager->assignRole('City Manager');

        $this->supplyManager = User::factory()->create();
        $this->supplyManager->assignRole('Supply Manager');

        $this->demandManager = User::factory()->create();
        $this->demandManager->assignRole('Demand Manager');

        $this->opsManager = User::factory()->create();
        $this->opsManager->assignRole('Operations Manager');

        $this->opsExecutive = User::factory()->create();
        $this->opsExecutive->assignRole('Operations Executive');

        $this->accountant = User::factory()->create();
        $this->accountant->assignRole('Accountant');

        $org = Organization::create([
            'name' => 'Dwelly Living Private Limited',
            'legal_name' => 'Dwelly Living Private Limited',
        ]);

        $branch = Branch::create([
            'organization_id' => $org->id,
            'name' => 'Guwahati Central',
            'code' => 'GH-01',
            'city' => 'Guwahati',
        ]);

        foreach ([$this->owner, $this->cityManager, $this->supplyManager, $this->demandManager, $this->opsManager, $this->opsExecutive, $this->accountant] as $u) {
            $u->branches()->attach($branch->id);
        }

        $this->ownerParty = Party::create([
            'display_name' => 'Bhupen Hazarika',
            'phone' => '+919876543210',
            'party_type' => 'individual',
        ]);

        $this->tenantParty = Party::create([
            'display_name' => 'Aditi Sharma',
            'phone' => '+919876543211',
            'party_type' => 'individual',
        ]);

        $this->property = Property::create([
            'code' => 'PROP-GH-501',
            'building_name' => 'Brahmaputra Enclave #5A',
            'address_line_1' => 'Uzan Bazar, Guwahati',
            'status' => 'occupied',
            'branch_id' => $branch->id,
        ]);

        $this->agreement = TenancyAgreement::create([
            'code' => 'TA-2026-0501',
            'property_id' => $this->property->id,
            'tenant_id' => $this->tenantParty->id,
            'branch_id' => $branch->id,
            'start_date' => now()->startOfMonth()->toDateString(),
            'end_date' => now()->addYear()->toDateString(),
            'rent_amount' => 30000.00,
            'security_deposit' => 60000.00,
            'status' => 'active',
        ]);

        $this->draftPayout = OwnerPayout::create([
            'branch_id' => $branch->id,
            'owner_id' => $this->ownerParty->id,
            'property_id' => $this->property->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'rent_collected' => 30000.00,
            'management_fee' => 3000.00,
            'advance_offset' => 0.00,
            'reserve_deduction' => 0.00,
            'amount' => 27000.00,
            'status' => 'draft',
        ]);

        $this->completedPayout = OwnerPayout::create([
            'branch_id' => $branch->id,
            'owner_id' => $this->ownerParty->id,
            'property_id' => $this->property->id,
            'period_start' => now()->subMonth()->startOfMonth()->toDateString(),
            'period_end' => now()->subMonth()->endOfMonth()->toDateString(),
            'rent_collected' => 30000.00,
            'management_fee' => 3000.00,
            'advance_offset' => 0.00,
            'reserve_deduction' => 0.00,
            'amount' => 27000.00,
            'status' => 'completed',
            'processed_at' => now()->subMonth(),
        ]);
    }

    public function test_accounting_panel_access_isolation(): void
    {
        $panel = Filament::getPanel('accounting');

        // Allowed roles: Business Owner, Accountant, City Manager (read-only city P&L)
        $this->assertTrue($this->owner->canAccessPanel($panel));
        $this->assertTrue($this->accountant->canAccessPanel($panel));
        $this->assertTrue($this->cityManager->canAccessPanel($panel));

        // Forbidden roles: Supply Manager, Demand Manager, Operations Manager, Operations Executive
        $this->assertFalse($this->supplyManager->canAccessPanel($panel));
        $this->assertFalse($this->demandManager->canAccessPanel($panel));
        $this->assertFalse($this->opsManager->canAccessPanel($panel));
        $this->assertFalse($this->opsExecutive->canAccessPanel($panel));

        // HTTP access verification
        $this->actingAs($this->supplyManager)->get('/accounting')->assertForbidden();
        $this->actingAs($this->demandManager)->get('/accounting')->assertForbidden();
        $this->actingAs($this->opsExecutive)->get('/accounting')->assertForbidden();
        $this->actingAs($this->opsManager)->get('/accounting')->assertForbidden();

        $this->actingAs($this->accountant)->get('/accounting')->assertSuccessful();
        $this->actingAs($this->cityManager)->get('/accounting')->assertSuccessful();
        $this->actingAs($this->owner)->get('/accounting')->assertSuccessful();
    }

    public function test_financial_operations_hub_access_and_action_boundaries(): void
    {
        // Hub Page Access
        $this->actingAs($this->owner);
        $this->assertTrue(FinancialOperationsHub::canAccess());

        $this->actingAs($this->accountant);
        $this->assertTrue(FinancialOperationsHub::canAccess());

        $this->actingAs($this->cityManager);
        $this->assertTrue(FinancialOperationsHub::canAccess());

        $this->actingAs($this->opsManager);
        $this->assertTrue(FinancialOperationsHub::canAccess()); // Ops Manager gets read-only receivables visibility

        $this->actingAs($this->supplyManager);
        $this->assertFalse(FinancialOperationsHub::canAccess());

        $this->actingAs($this->demandManager);
        $this->assertFalse(FinancialOperationsHub::canAccess());

        $this->actingAs($this->opsExecutive);
        $this->assertFalse(FinancialOperationsHub::canAccess());

        // Cash / Ledger Recording Permissions (Accountant & Owner only)
        $this->assertTrue($this->accountant->can('billing.receipt.record'));
        $this->assertTrue($this->accountant->can('billing.deposit.record'));
        $this->assertTrue($this->accountant->can('billing.bill.pay'));
        $this->assertTrue($this->accountant->can('billing.advance.record'));

        $this->assertFalse($this->cityManager->can('billing.receipt.record'));
        $this->assertFalse($this->cityManager->can('billing.deposit.record'));
        $this->assertFalse($this->cityManager->can('billing.bill.pay'));
        $this->assertFalse($this->cityManager->can('billing.advance.record'));

        $this->assertFalse($this->opsManager->can('billing.receipt.record'));
        $this->assertFalse($this->opsManager->can('billing.deposit.record'));
        $this->assertFalse($this->opsManager->can('billing.bill.pay'));
    }

    public function test_bulk_rent_demands_generation_and_resource_rbac(): void
    {
        // BulkGenerateMonthlyRent Page Access
        $this->actingAs($this->owner);
        $this->assertTrue(BulkGenerateMonthlyRent::canAccess());

        $this->actingAs($this->accountant);
        $this->assertTrue(BulkGenerateMonthlyRent::canAccess());

        $this->actingAs($this->cityManager);
        $this->assertTrue(BulkGenerateMonthlyRent::canAccess()); // City preview summary

        $this->actingAs($this->opsManager);
        $this->assertFalse(BulkGenerateMonthlyRent::canAccess());

        $this->actingAs($this->supplyManager);
        $this->assertFalse(BulkGenerateMonthlyRent::canAccess());

        $this->actingAs($this->demandManager);
        $this->assertFalse(BulkGenerateMonthlyRent::canAccess());

        $this->actingAs($this->opsExecutive);
        $this->assertFalse(BulkGenerateMonthlyRent::canAccess());

        // Generation Execution Gate
        $this->assertTrue($this->accountant->can('billing.rent.generate'));
        $this->assertTrue($this->owner->can('billing.rent.generate'));
        $this->assertFalse($this->cityManager->can('billing.rent.generate'));
        $this->assertFalse($this->opsManager->can('billing.rent.generate'));

        // RentDemandsResource RBAC
        $this->actingAs($this->accountant);
        $this->assertTrue(RentDemandsResource::canViewAny());
        $this->assertTrue(RentDemandsResource::canCreate());

        $this->actingAs($this->cityManager);
        $this->assertTrue(RentDemandsResource::canViewAny());
        $this->assertFalse(RentDemandsResource::canCreate());

        $this->actingAs($this->opsManager);
        $this->assertTrue(RentDemandsResource::canViewAny()); // Receivables visibility
        $this->assertFalse(RentDemandsResource::canCreate());

        $this->actingAs($this->supplyManager);
        $this->assertFalse(RentDemandsResource::canViewAny());

        $this->actingAs($this->demandManager);
        $this->assertFalse(RentDemandsResource::canViewAny());
    }

    public function test_owner_payout_policy_and_fiduciary_disbursement_gate(): void
    {
        // ViewAny & View Permissions
        $this->assertTrue($this->owner->can('viewAny', OwnerPayout::class));
        $this->assertTrue($this->accountant->can('viewAny', OwnerPayout::class));
        $this->assertTrue($this->cityManager->can('viewAny', OwnerPayout::class));
        $this->assertTrue($this->opsManager->can('viewAny', OwnerPayout::class));
        $this->assertTrue($this->supplyManager->can('viewAny', OwnerPayout::class));

        $this->assertFalse($this->demandManager->can('viewAny', OwnerPayout::class));
        $this->assertFalse($this->opsExecutive->can('viewAny', OwnerPayout::class));

        // Fiduciary Bank Disbursement Gate (Accountant & Owner ONLY)
        $this->assertTrue($this->owner->can('disburse', $this->draftPayout));
        $this->assertTrue($this->accountant->can('disburse', $this->draftPayout));

        $this->assertFalse($this->cityManager->can('disburse', $this->draftPayout));
        $this->assertFalse($this->opsManager->can('disburse', $this->draftPayout));
        $this->assertFalse($this->supplyManager->can('disburse', $this->draftPayout));
        $this->assertFalse($this->demandManager->can('disburse', $this->draftPayout));
        $this->assertFalse($this->opsExecutive->can('disburse', $this->draftPayout));

        // Dispute Hold & Reserve Management
        $this->assertTrue($this->opsManager->can('manageHold', $this->draftPayout)); // Ops Manager can flag disputes to freeze payout
        $this->assertTrue($this->cityManager->can('manageHold', $this->draftPayout));
        $this->assertTrue($this->accountant->can('manageHold', $this->draftPayout));
        $this->assertFalse($this->supplyManager->can('manageHold', $this->draftPayout));

        // Commission Validation & Statement Generation
        $this->assertTrue($this->supplyManager->can('validateCommission', $this->draftPayout)); // Verify sourced deals
        $this->assertTrue($this->supplyManager->can('generateStatement', $this->draftPayout));
        $this->assertTrue($this->cityManager->can('generateStatement', $this->draftPayout));
        $this->assertTrue($this->accountant->can('generateStatement', $this->draftPayout));

        // Immutability: Completed Payouts cannot be edited or deleted
        $this->assertFalse($this->accountant->can('update', $this->completedPayout));
        $this->assertFalse($this->accountant->can('delete', $this->completedPayout));
        $this->assertFalse($this->owner->can('update', $this->completedPayout));
        $this->assertFalse($this->owner->can('delete', $this->completedPayout));
    }

    public function test_bulk_generate_owner_payouts_page_and_resource_rbac(): void
    {
        // BulkGenerateOwnerPayouts Page Access
        $this->actingAs($this->owner);
        $this->assertTrue(BulkGenerateOwnerPayouts::canAccess(), 'owner canAccess');

        $this->actingAs($this->accountant);
        $this->assertTrue(BulkGenerateOwnerPayouts::canAccess(), 'accountant canAccess');

        $this->actingAs($this->cityManager);
        $this->assertTrue(BulkGenerateOwnerPayouts::canAccess(), 'cityManager canAccess');

        $this->actingAs($this->opsManager);
        $this->assertFalse(BulkGenerateOwnerPayouts::canAccess(), 'opsManager cannot access');

        $this->actingAs($this->supplyManager);
        $this->assertFalse(BulkGenerateOwnerPayouts::canAccess(), 'supplyManager cannot access');

        $this->actingAs($this->demandManager);
        $this->assertFalse(BulkGenerateOwnerPayouts::canAccess(), 'demandManager cannot access');

        $this->actingAs($this->opsExecutive);
        $this->assertFalse(BulkGenerateOwnerPayouts::canAccess(), 'opsExecutive cannot access');

        // Execution Gate: City Manager cannot execute disbursement
        $this->actingAs($this->cityManager);
        $page = new BulkGenerateOwnerPayouts;
        try {
            $page->disburseSelected();
            $this->fail('City Manager should not be able to disburse owner payouts');
        } catch (HttpException $e) {
            $this->assertEquals(403, $e->getStatusCode(), 'City Manager disburse returns 403');
        }

        // OwnerPayoutResource RBAC
        $this->actingAs($this->accountant);
        $this->assertTrue(OwnerPayoutResource::canViewAny(), 'accountant canViewAny');
        $this->assertTrue(OwnerPayoutResource::canCreate(), 'accountant canCreate');

        $this->actingAs($this->cityManager);
        $this->assertTrue(OwnerPayoutResource::canViewAny(), 'cityManager canViewAny');
        $this->assertFalse(OwnerPayoutResource::canCreate(), 'cityManager cannot create');

        $this->actingAs($this->supplyManager);
        $this->assertTrue(OwnerPayoutResource::canViewAny(), 'supplyManager canViewAny');
        $this->assertFalse(OwnerPayoutResource::canCreate(), 'supplyManager cannot create');

        $this->actingAs($this->demandManager);
        $this->assertFalse(OwnerPayoutResource::canViewAny(), 'demandManager cannot canViewAny');
    }

    public function test_double_entry_accounting_internal_controls(): void
    {
        // Chart of Accounts (CoA) Governance
        $this->assertTrue($this->owner->can('create', Account::class));
        $this->assertTrue($this->accountant->can('create', Account::class));
        $this->assertFalse($this->cityManager->can('create', Account::class));
        $this->assertFalse($this->opsManager->can('create', Account::class));

        // Bank Accounts & Reconciliation (Fiduciary Controller only)
        $this->assertTrue($this->owner->can('create', BankAccount::class));
        $this->assertTrue($this->accountant->can('create', BankAccount::class));
        $this->assertFalse($this->cityManager->can('create', BankAccount::class));
        $this->assertFalse($this->cityManager->can('viewAny', BankAccount::class));

        // Manual Journal Posting
        $this->assertTrue($this->owner->can('create', Transaction::class));
        $this->assertTrue($this->accountant->can('create', Transaction::class));
        $this->assertFalse($this->cityManager->can('create', Transaction::class));
        $this->assertFalse($this->opsManager->can('create', Transaction::class));

        // Financial Dashboard Access
        $this->actingAs($this->owner);
        $this->assertTrue(FinancialDashboard::canAccess());

        $this->actingAs($this->cityManager);
        $this->assertTrue(FinancialDashboard::canAccess());

        $this->actingAs($this->accountant);
        $this->assertTrue(FinancialDashboard::canAccess());

        $this->actingAs($this->opsManager);
        $this->assertFalse(FinancialDashboard::canAccess());

        $this->actingAs($this->supplyManager);
        $this->assertFalse(FinancialDashboard::canAccess());

        $this->actingAs($this->demandManager);
        $this->assertFalse(FinancialDashboard::canAccess());
    }
}
