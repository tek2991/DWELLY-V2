<?php

namespace Tests\Feature;

use App\Domain\Mou\Enums\MouType;
use App\Domain\Mou\Models\Mou;
use App\Domain\Mou\Services\MouWorkflowService;
use App\Domain\Mou\Services\PropertyUpdateMouService;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Opportunity\Models\FinancialModel;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Property\Models\PropertyFinancialTerm;
use App\Filament\Resources\Properties\Pages\PropertyFinancials;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

class PropertyUpdateMouTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Property $property;
    protected Party $party;
    protected FinancialModel $financialModel;

    protected function setUp(): void
    {
        parent::setUp();

        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('Business Owner');
        $this->actingAs($this->user);

        $this->financialModel = FinancialModel::create([
            'slug' => 'rev-share-15',
            'name' => 'Revenue Share 15%',
            'description' => '15% revenue share model',
            'fee_collection' => 'Monthly payout deduction',
            'is_active' => true,
        ]);

        $this->party = Party::create([
            'party_type' => 'individual',
            'display_name' => 'Jane Owner',
            'phone' => '9876543210',
            'email' => 'jane@example.com',
        ]);

        $opportunity = Opportunity::create([
            'number' => 'OPP-800',
            'title' => 'Test Opportunity 800',
            'owner_name' => 'Jane Owner',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $this->user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::CONVERTED,
        ]);

        $this->property = Property::create([
            'code' => 'PROP-800',
            'building_name' => 'Test Apartment',
            'address_line_1' => '123 Main St',
            'status' => 'active',
        ]);

        // Create initial Onboarding MOU
        Mou::create([
            'number' => 'MOU-2026-0001',
            'property_id' => $this->property->id,
            'opportunity_id' => $opportunity->id,
            'party_id' => $this->party->id,
            'type' => MouType::ONBOARDING,
            'status' => MouStatus::CONVERTED,
            'legal_terms' => [
                'rent_amount' => 20000,
                'fee_percentage' => 12,
                'financial_model_name' => 'Standard 12%',
            ],
            'bank_details' => [
                'beneficiary_name' => 'Jane Owner',
                'bank_name' => 'HDFC Bank',
                'account_number' => '111122223333',
                'ifsc_code' => 'HDFC0001234',
                'bank_address' => 'Guwahati',
            ],
        ]);

        PropertyFinancialTerm::create([
            'property_id' => $this->property->id,
            'pricing_model' => 'Standard 12%',
            'fee_percentage' => 12,
            'effective_from' => now()->subMonths(6),
        ]);
    }

    public function test_can_initiate_and_verify_pricing_update_mou()
    {
        $service = app(PropertyUpdateMouService::class);
        $mouWorkflow = app(MouWorkflowService::class);

        // 1. Initiate update
        $updateMou = $service->initiateUpdate($this->property, MouType::PRICING_UPDATE, [
            'financial_model_id' => $this->financialModel->id,
            'fee_percentage' => 15,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $this->assertEquals(MouType::PRICING_UPDATE, $updateMou->type);
        $this->assertEquals(MouStatus::DRAFT, $updateMou->status);
        $this->assertEquals(15, $updateMou->legal_terms['fee_percentage']);
        $this->assertEquals('Revenue Share 15%', $updateMou->legal_terms['financial_model_name']);

        // 2. Generate PDF
        $mouWorkflow->generatePdf($updateMou);
        $updateMou->refresh();
        $this->assertEquals(MouStatus::PDF_GENERATED, $updateMou->status);
        $this->assertTrue($updateMou->hasMedia('draft_pdf'));

        // 3. Upload signed copy
        $file = UploadedFile::fake()->create('signed_addendum.pdf', 100, 'application/pdf');
        $path = $file->store('temp-signed-pdfs', 'public');

        $mouWorkflow->uploadSignedCopy($updateMou, $path);
        $updateMou->refresh();
        $this->assertEquals(MouStatus::SIGNED_COPY_UPLOADED, $updateMou->status);

        // 4. Verify MOU (triggers commit)
        $mouWorkflow->verify($updateMou);
        $updateMou->refresh();
        $this->assertEquals(MouStatus::VERIFIED, $updateMou->status);

        // Verify active PropertyFinancialTerm created
        $latestTerm = $this->property->financialTerms()->latest('effective_from')->first();
        $this->assertEquals('Revenue Share 15%', $latestTerm->pricing_model);
        $this->assertEquals(15, $latestTerm->fee_percentage);
    }

    public function test_can_initiate_and_verify_bank_details_update_mou()
    {
        $service = app(PropertyUpdateMouService::class);
        $mouWorkflow = app(MouWorkflowService::class);

        // Initiate bank update
        $updateMou = $service->initiateUpdate($this->property, MouType::BANK_DETAILS_UPDATE, [
            'beneficiary_name' => 'Jane Updated Owner',
            'bank_name' => 'ICICI Bank',
            'account_number' => '999988887777',
            'ifsc_code' => 'ICIC0005678',
            'bank_address' => 'Guwahati Downtown',
        ]);

        $this->assertEquals(MouType::BANK_DETAILS_UPDATE, $updateMou->type);
        $this->assertEquals('ICICI Bank', $updateMou->bank_details['bank_name']);

        // Generate PDF & upload signed
        $mouWorkflow->generatePdf($updateMou);
        $file = UploadedFile::fake()->create('signed_bank_addendum.pdf', 100, 'application/pdf');
        $path = $file->store('temp-signed-pdfs', 'public');
        $mouWorkflow->uploadSignedCopy($updateMou, $path);

        // Verify (triggers bank account creation & primary update)
        $mouWorkflow->verify($updateMou);

        $primaryBankAccount = $this->party->bankAccounts()->where('is_primary', true)->first();
        $this->assertNotNull($primaryBankAccount);
        $this->assertEquals('ICICI Bank', $primaryBankAccount->bank_name);
        $this->assertEquals('999988887777', $primaryBankAccount->account_number);
    }

    public function test_property_financials_page_renders_active_and_pending_mou_workflow()
    {
        Livewire::test(PropertyFinancials::class, [
            'record' => $this->property->getKey(),
        ])
            ->assertFormSet(['pricing_model' => 'Standard 12%']);
    }

    public function test_access_control_on_property_financials_page()
    {
        // 1. Business Owner has access
        $this->actingAs($this->user);
        $this->assertTrue(PropertyFinancials::canAccess());

        // 2. Operations Manager has access
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Operations Manager', 'guard_name' => 'web']);
        $manager = User::factory()->create();
        $manager->assignRole('Operations Manager');
        $this->actingAs($manager);
        $this->assertTrue(PropertyFinancials::canAccess());

        // 3. Accountant has access
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Accountant', 'guard_name' => 'web']);
        $accountant = User::factory()->create();
        $accountant->assignRole('Accountant');
        $this->actingAs($accountant);
        $this->assertTrue(PropertyFinancials::canAccess());

        // 4. Operations Executive without finance.access is forbidden
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Operations Executive', 'guard_name' => 'web']);
        $executive = User::factory()->create();
        $executive->assignRole('Operations Executive');
        $this->actingAs($executive);
        $this->assertFalse(PropertyFinancials::canAccess());
    }

    public function test_can_initiate_and_verify_signatory_authority_update_and_persist_baseline()
    {
        $this->actingAs($this->user);
        $service = app(PropertyUpdateMouService::class);
        $mouWorkflow = app(MouWorkflowService::class);

        // 1. Initiate signatory update
        $updateMou = $service->initiateUpdate($this->property, MouType::SIGN_AUTHORITY_UPDATE, [
            'is_signatory_different' => true,
            'signatory_details' => [
                'name' => 'Robert Smith',
                'relation' => 'Power of Attorney',
                'phone' => '9988776655',
                'email' => 'robert@example.com',
                'aadhar_number' => '123456789012',
                'pan_number' => 'ABCDE1234F',
            ],
        ]);

        $this->assertEquals(MouType::SIGN_AUTHORITY_UPDATE, $updateMou->type);
        $this->assertTrue($updateMou->is_signatory_different);
        $this->assertEquals('Robert Smith', $updateMou->signatory_details['name']);

        // 2. Generate PDF & upload signed
        $mouWorkflow->generatePdf($updateMou);
        $file = UploadedFile::fake()->create('signed_signatory_addendum.pdf', 100, 'application/pdf');
        $path = $file->store('temp-signed-pdfs', 'public');
        $mouWorkflow->uploadSignedCopy($updateMou, $path);

        // 3. Verify signatory MOU
        $mouWorkflow->verify($updateMou);
        $updateMou->refresh();
        $this->assertEquals(MouStatus::VERIFIED, $updateMou->status);

        // 4. Test PropertyFinancials form reflects verified signatory
        Livewire::test(PropertyFinancials::class, [
            'record' => $this->property->getKey(),
        ])
            ->assertFormSet([
                'is_signatory_different' => true,
                'signatory_name' => 'Robert Smith',
                'signatory_relation' => 'Power of Attorney',
            ]);

        // 5. Subsequent Pricing update inherits verified signatory baseline
        $pricingUpdateMou = $service->initiateUpdate($this->property, MouType::PRICING_UPDATE, [
            'financial_model_id' => $this->financialModel->id,
            'fee_percentage' => 14,
            'start_date' => now()->format('Y-m-d'),
        ]);

        $this->assertTrue($pricingUpdateMou->is_signatory_different);
        $this->assertEquals('Robert Smith', $pricingUpdateMou->signatory_details['name']);
    }

    public function test_active_mou_getters_return_null_when_only_draft_or_cancelled_mou_exists()
    {
        $newOpportunity = Opportunity::create([
            'number' => 'OPP-999',
            'title' => 'Test Opportunity 999',
            'owner_name' => 'John Owner',
            'owner_phone' => '9988771122',
            'assigned_user_id' => $this->user->id,
            'status' => \App\Domain\Opportunity\Enums\OpportunityStatus::NEW,
        ]);

        $newProperty = Property::create([
            'code' => 'PROP-999',
            'building_name' => 'Unverified Building',
            'address_line_1' => '999 North St',
            'status' => 'draft',
        ]);

        // Create only a DRAFT MOU
        Mou::create([
            'number' => 'MOU-2026-9999',
            'opportunity_id' => $newOpportunity->id,
            'property_id' => $newProperty->id,
            'type' => MouType::PRICING_UPDATE,
            'status' => MouStatus::DRAFT,
            'legal_terms' => ['fee_percentage' => 10],
        ]);

        $component = Livewire::test(PropertyFinancials::class, [
            'record' => $newProperty->getKey(),
        ]);

        $this->assertNull($component->instance()->activePricingMou);
        $this->assertNull($component->instance()->activeBankMou);
        $this->assertNull($component->instance()->activeSignatoryMou);
    }

    public function test_raw_viewers_gracefully_handle_missing_file_on_disk()
    {
        $renderedPdfViewer = (string) $this->blade('<x-pdf-viewer-raw path="/nonexistent/file.pdf" />');
        $this->assertStringContainsString('Document file not found on disk', $renderedPdfViewer);

        $renderedDocViewer = (string) $this->blade('<x-document-viewer-raw path="/nonexistent/file.pdf" mimeType="application/pdf" />');
        $this->assertStringContainsString('Document file not found on disk', $renderedDocViewer);
    }
}
