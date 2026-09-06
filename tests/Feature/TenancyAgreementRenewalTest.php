<?php

namespace Tests\Feature;

use App\Domain\Agreement\Actions\ActivateTenancyAction;
use App\Domain\Agreement\Actions\DraftTenancyAgreementAction;
use App\Domain\Agreement\Actions\RenewTenancyAgreementAction;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Billing\RentDemandsResource;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tek2991\Accounting\Filament\Resources\Sales\Invoices\InvoiceResource;
use Tek2991\Accounting\Models\Invoice;
use Tests\TestCase;

class TenancyAgreementRenewalTest extends TestCase
{
    use RefreshDatabase;

    private function createSampleActiveAgreement(): array
    {
        $user = User::factory()->create();

        $property = Property::create([
            'building_name' => 'Hillview Apartments Flat 4B',
            'address_line_1' => 'Zoo Road, Guwahati',
            'status' => 'occupied',
        ]);

        $tenant = Party::create([
            'display_name' => 'Rahul Sharma',
            'phone' => '+91 98765 43210',
            'email' => 'rahul@example.com',
            'party_type' => 'individual',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'tenant_id' => $tenant->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::COMPLETED,
            'is_locked' => true,
        ]);

        $agreement = TenancyAgreement::create([
            'property_id' => $property->id,
            'audit_id' => $audit->id,
            'code' => 'TNC-2025-00100',
            'status' => 'active',
            'is_renewal' => false,
            'documentation_charge' => 1500.00,
            'rent_amount' => 20000.00,
            'first_month_rent' => 20000.00,
            'security_deposit' => 40000.00,
            'start_date' => '2025-08-01',
            'end_date' => '2026-06-30',
            'signed_by_tenant' => true,
            'signed_at' => '2025-08-01',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2025-08-01',
            'tenant_bank_details' => [
                'beneficiary_name' => 'Rahul Sharma',
                'account_number' => '1234567890',
                'bank_name' => 'HDFC Bank',
                'ifsc_code' => 'HDFC0001234',
                'account_type' => 'Savings',
            ],
        ]);

        $agreement->roles()->create([
            'party_id' => $tenant->id,
            'role_type' => 'Primary Tenant',
            'is_primary' => true,
        ]);

        return [$user, $property, $tenant, $audit, $agreement];
    }

    public function test_can_renew_active_tenancy_agreement(): void
    {
        [$user, $property, $tenant, $audit, $previousAgreement] = $this->createSampleActiveAgreement();

        $action = app(RenewTenancyAgreementAction::class);

        $renewalData = [
            'start_date' => '2026-07-01',
            'end_date' => '2027-05-31',
            'rent_amount' => 21000.00,
            'security_deposit' => 40000.00,
            'documentation_charge' => 1000.00,
            'renewal_notes' => 'Renewed with 5% escalation upon mutual consent.',
        ];

        $renewalAgreement = $action->execute($previousAgreement, $renewalData, $user);

        $this->assertNotNull($renewalAgreement);
        $this->assertEquals('draft', $renewalAgreement->status);
        $this->assertTrue($renewalAgreement->is_renewal);
        $this->assertEquals($previousAgreement->id, $renewalAgreement->previous_agreement_id);
        $this->assertEquals(21000.00, (float) $renewalAgreement->rent_amount);
        $this->assertEquals(40000.00, (float) $renewalAgreement->security_deposit);
        $this->assertEquals(1000.00, (float) $renewalAgreement->documentation_charge);
        $this->assertEquals('2026-07-01', $renewalAgreement->start_date->format('Y-m-d'));
        $this->assertEquals('2027-05-31', $renewalAgreement->end_date->format('Y-m-d'));
        $this->assertEquals($audit->id, $renewalAgreement->audit_id);
        $this->assertEquals('Rahul Sharma', $renewalAgreement->tenant_bank_details['beneficiary_name']);
        $this->assertEquals('1234567890', $renewalAgreement->tenant_bank_details['account_number']);

        // Verify primary tenant party relationship carried over
        $this->assertCount(1, $renewalAgreement->tenants);
        $this->assertEquals($tenant->id, $renewalAgreement->tenants->first()->id);
        $this->assertEquals($tenant->id, $renewalAgreement->primaryTenant->party_id);

        // Verify Eloquent relationships
        $this->assertEquals($previousAgreement->id, $renewalAgreement->previousAgreement->id);
        $this->assertTrue($previousAgreement->renewals->contains($renewalAgreement));
        $this->assertEquals($renewalAgreement->id, $previousAgreement->latestRenewal->id);
    }

    public function test_activating_renewal_agreement_transitions_previous_to_renewed(): void
    {
        [$user, $property, $tenant, $audit, $previousAgreement] = $this->createSampleActiveAgreement();

        $action = app(RenewTenancyAgreementAction::class);
        $renewalAgreement = $action->execute($previousAgreement, [
            'start_date' => '2026-07-01',
            'end_date' => '2027-05-31',
            'rent_amount' => 21000.00,
            'security_deposit' => 40000.00,
            'documentation_charge' => 1000.00,
        ], $user);

        // Complete execution prerequisites on renewal agreement
        $renewalAgreement->update([
            'signed_by_tenant' => true,
            'signed_at' => '2026-07-01',
            'keys_handed_over' => true,
            'keys_handed_over_at' => '2026-07-01',
        ]);

        $file = UploadedFile::fake()->create('renewal_signed.pdf', 100, 'application/pdf');
        $renewalAgreement->addMedia($file)->toMediaCollection('signed_agreement');

        // Activate renewal agreement
        app(ActivateTenancyAction::class)->execute($renewalAgreement->fresh(), $user);

        $previousAgreement->refresh();
        $renewalAgreement->refresh();

        $this->assertEquals('renewed', $previousAgreement->status);
        $this->assertEquals('active', $renewalAgreement->status);
        $this->assertEquals('occupied', $property->fresh()->status);
    }

    public function test_documentation_charge_invoice_generation_for_fresh_and_renewal_agreements(): void
    {
        [$user, $property, $tenant, $audit, $previousAgreement] = $this->createSampleActiveAgreement();

        $provisioning = app(AccountingProvisioningService::class);

        // 1. Fresh Agreement (₹1,500 default)
        $invoiceFresh = $provisioning->generateDocumentationChargeInvoice($previousAgreement);
        $this->assertNotNull($invoiceFresh);
        $this->assertEquals(1500.00, (float) $invoiceFresh->grand_total);
        $this->assertEquals($invoiceFresh->id, $previousAgreement->fresh()->documentation_invoice_id);
        $this->assertEquals('documentation_charge', $invoiceFresh->document_snapshot['invoice_category'] ?? null);

        // Idempotency: calling again returns the same invoice
        $invoiceSecondCall = $provisioning->generateDocumentationChargeInvoice($previousAgreement->fresh());
        $this->assertEquals($invoiceFresh->id, $invoiceSecondCall->id);

        // 2. Renewal Agreement (₹1,000)
        $action = app(RenewTenancyAgreementAction::class);
        $renewalAgreement = $action->execute($previousAgreement, [
            'start_date' => '2026-07-01',
            'end_date' => '2027-05-31',
            'rent_amount' => 21000.00,
            'security_deposit' => 40000.00,
            'documentation_charge' => 1000.00,
        ], $user);

        $invoiceRenewal = $provisioning->generateDocumentationChargeInvoice($renewalAgreement);
        $this->assertNotNull($invoiceRenewal);
        $this->assertEquals(1000.00, (float) $invoiceRenewal->grand_total);
        $this->assertEquals($invoiceRenewal->id, $renewalAgreement->fresh()->documentation_invoice_id);
        $this->assertEquals('documentation_charge', $invoiceRenewal->document_snapshot['invoice_category'] ?? null);
        $this->assertTrue((bool) ($invoiceRenewal->document_snapshot['is_renewal'] ?? false));
    }

    public function test_query_isolation_between_rent_demands_and_invoices(): void
    {
        [$user, $property, $tenant, $audit, $previousAgreement] = $this->createSampleActiveAgreement();

        $provisioning = app(AccountingProvisioningService::class);
        $docInvoice = $provisioning->generateDocumentationChargeInvoice($previousAgreement);

        // RentDemandsResource should NOT list documentation charge invoices
        $rentDemandsQuery = RentDemandsResource::getEloquentQuery();
        $this->assertFalse($rentDemandsQuery->where('id', $docInvoice->id)->exists());

        // InvoiceResource SHOULD include documentation charge invoices
        $accountingInvoicesQuery = InvoiceResource::getEloquentQuery();
        $this->assertTrue($accountingInvoicesQuery->where('id', $docInvoice->id)->exists());
    }
}
