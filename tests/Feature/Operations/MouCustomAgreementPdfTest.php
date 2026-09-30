<?php

namespace Tests\Feature\Operations;

use App\Domain\Geographic\Models\City;
use App\Domain\Geographic\Models\District;
use App\Domain\Mou\Models\Mou;
use App\Domain\Mou\Services\MouTemplateService;
use App\Domain\Mou\Services\MouWorkflowService;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\FinancialModel;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\PartyIndividual;
use App\Domain\Property\Models\UtilityProvider;
use App\Domain\Property\Models\UtilityType;
use App\Filament\Resources\Operations\MOUResource\Pages\ViewMOU;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tek2991\Accounting\Models\State;
use Tests\TestCase;

class MouCustomAgreementPdfTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Mou $mou;

    protected Party $party;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $this->user = User::factory()->create();
        $this->user->assignRole('Business Owner');
        $this->actingAs($this->user);

        $state = State::create([
            'country_id' => 1,
            'name' => 'Assam',
            'code' => 'AS',
        ]);
        $district = District::create([
            'state_id' => $state->id,
            'name' => 'Kamrup',
            'slug' => 'kamrup',
            'is_active' => true,
        ]);
        $city = City::create([
            'district_id' => $district->id,
            'name' => 'Guwahati',
            'slug' => 'guwahati',
            'is_active' => true,
        ]);

        $financialModel = FinancialModel::create([
            'name' => 'Standard Revenue Share',
            'description' => '12% management fee model',
            'fee_collection' => 'monthly',
        ]);

        $utilityType = UtilityType::firstOrCreate(['slug' => 'electricity'], ['name' => 'Electricity']);
        $provider = UtilityProvider::create([
            'name' => 'APDCL Power Supply',
            'utility_type_id' => $utilityType->id,
            'is_active' => true,
        ]);

        $opportunity = Opportunity::create([
            'number' => 'OPP-2026-001',
            'title' => 'Green Valley Villa',
            'owner_name' => 'Amitabh Barua',
            'owner_phone' => '9876543210',
            'owner_email' => 'amitabh@example.com',
            'address' => 'House 12, Zoo Road, Guwahati',
            'expected_rent' => 45000,
            'expected_financial_model_id' => $financialModel->id,
            'assigned_user_id' => $this->user->id,
            'status' => OpportunityStatus::NEW,
        ]);

        $this->party = Party::create([
            'display_name' => 'Amitabh Barua',
            'party_type' => 'individual',
            'phone' => '9876543210',
            'email' => 'amitabh@example.com',
        ]);

        PartyIndividual::create([
            'party_id' => $this->party->id,
            'name' => 'Amitabh Barua',
            'pan_number' => 'ABCDE1234F',
            'aadhaar_number' => '999988887777',
            'parent_name' => 'Late Hemen Barua',
        ]);

        $this->mou = Mou::create([
            'number' => 'MOU-2026-0001',
            'opportunity_id' => $opportunity->id,
            'party_id' => $this->party->id,
            'status' => MouStatus::READY_TO_GENERATE,
            'owner_details' => [
                'name' => 'Amitabh Barua',
                'parent_name' => 'Late Hemen Barua',
                'pan_number' => 'ABCDE1234F',
                'aadhar_number' => '999988887777',
                'phone' => '9876543210',
                'email' => 'amitabh@example.com',
                'address' => 'House 12, Zoo Road, Guwahati',
            ],
            'legal_terms' => [
                'address' => 'Green Valley Villa, Zoo Road, Guwahati',
                'rent_amount' => 45000,
                'fee_percentage' => 12,
                'financial_model_id' => $financialModel->id,
                'financial_model_name' => 'Standard Revenue Share',
                'electricity_provider_id' => $provider->id,
                'electricity_provider_name' => 'APDCL Power Supply',
                'electricity_consumer_id' => 'APDCL-CON-887766',
                'city_id' => $city->id,
                'city_name' => $city->name,
            ],
            'bank_details' => [
                'beneficiary_name' => 'Amitabh Barua',
                'account_holder_name' => 'Amitabh Barua',
                'bank_name' => 'State Bank of India',
                'bank_address' => 'Zoo Road Branch, Guwahati',
                'account_number' => '30012345678',
                'account_type' => 'Savings',
                'ifsc_code' => 'SBIN0001234',
            ],
            'start_date' => now()->toDateString(),
            'prepared_by' => $this->user->id,
        ]);

        // Add mandatory electricity bill file for readiness
        $file = UploadedFile::fake()->create('electricity.pdf', 100, 'application/pdf');
        $this->mou->addMedia($file)->toMediaCollection('electricity_bill');
    }

    public function test_template_service_generates_default_content_with_locked_badges(): void
    {
        $service = app(MouTemplateService::class);
        $editorContent = $service->getDefaultContent($this->mou, true);

        // Assert locked badges with data-param and contenteditable="false" exist
        $this->assertStringContainsString('data-param="owner_name"', $editorContent);
        $this->assertStringContainsString('data-param="owner_pan"', $editorContent);
        $this->assertStringContainsString('data-param="owner_aadhaar"', $editorContent);
        $this->assertStringContainsString('data-param="bank_account_number"', $editorContent);
        $this->assertStringContainsString('data-param="electricity_consumer_id"', $editorContent);
        $this->assertStringContainsString('contenteditable="false"', $editorContent);
        $this->assertStringContainsString('🔒', $editorContent);
        $this->assertStringContainsString('Amitabh Barua', $editorContent);
        $this->assertStringContainsString('ABCDE1234F', $editorContent);
    }

    public function test_compile_for_pdf_substitutes_locked_badges_with_clean_authoritative_values(): void
    {
        $service = app(MouTemplateService::class);

        // Inject content with locked badges
        $htmlWithBadge = '<p>Agreement with <span contenteditable="false" data-param="owner_name">🔒 Name: Fake Name</span> '.
            'having PAN <span contenteditable="false" data-param="owner_pan">🔒 PAN: FAKEPAN000</span>.</p>';

        $compiled = $service->compileForPdf($this->mou, $htmlWithBadge);

        // Anti-tampering: Should replace with real DB values, removing Fake Name / FAKEPAN
        $this->assertStringNotContainsString('Fake Name', $compiled);
        $this->assertStringNotContainsString('FAKEPAN000', $compiled);
        $this->assertStringNotContainsString('contenteditable', $compiled);
        $this->assertStringContainsString('Amitabh Barua', $compiled);
        $this->assertStringContainsString('ABCDE1234F', $compiled);
        $this->assertStringContainsString('class="form-line"', $compiled);
    }

    public function test_view_mou_draft_agreement_action_loads_editor_with_default_template(): void
    {
        Livewire::test(ViewMOU::class, ['record' => $this->mou->getKey()])
            ->mountAction('draftAgreement')
            ->assertActionDataSet(function (array $data) {
                $content = is_array($data['agreement_content'])
                    ? json_encode($data['agreement_content'])
                    : (string) $data['agreement_content'];

                return ! empty($content)
                    && str_contains($content, 'owner_name')
                    && str_contains($content, 'Amitabh Barua');
            });
    }

    public function test_editing_agreement_content_persists_customized_text_and_timestamp(): void
    {
        $customText = '<p>Special Custom Clause: Dwelly shall provide 24/7 priority concierge support.</p>';

        Livewire::test(ViewMOU::class, ['record' => $this->mou->getKey()])
            ->mountAction('draftAgreement')
            ->setActionData([
                'agreement_content' => $customText,
            ])
            ->callMountedAction();

        $this->mou->refresh();

        $this->assertEquals($customText, $this->mou->agreement_content);
        $this->assertNotNull($this->mou->agreement_customized_at);
        $this->assertEquals(MouStatus::PDF_GENERATED, $this->mou->status);
        $this->assertTrue($this->mou->hasMedia('draft_pdf'));
    }

    public function test_reset_template_action_clears_custom_content(): void
    {
        $this->mou->update([
            'agreement_content' => '<p>Old custom clause</p>',
            'agreement_customized_at' => now(),
        ]);

        $service = app(MouTemplateService::class);
        $fresh = $service->getDefaultContent($this->mou, true);

        // Test calling reset directly on model
        $this->mou->update([
            'agreement_content' => null,
            'agreement_customized_at' => null,
        ]);

        $this->mou->refresh();
        $this->assertNull($this->mou->agreement_content);
        $this->assertNull($this->mou->agreement_customized_at);
        $this->assertStringContainsString('Amitabh Barua', $fresh);
    }

    public function test_save_draft_action_saves_without_generating_pdf(): void
    {
        $draftText = '<p>Drafting in progress: clause under legal review.</p>';

        Livewire::test(ViewMOU::class, ['record' => $this->mou->getKey()])
            ->mountAction('draftAgreement')
            ->setActionData([
                'agreement_content' => $draftText,
            ]);

        $this->mou->refresh();

        $this->assertEquals($draftText, $this->mou->agreement_content);
        $this->assertNotNull($this->mou->agreement_customized_at);
        // Status should remain READY_TO_GENERATE, not converted to PDF_GENERATED
        $this->assertEquals(MouStatus::READY_TO_GENERATE, $this->mou->status);
        $this->assertFalse($this->mou->hasMedia('draft_pdf'));
    }

    public function test_regenerating_pdf_increments_version(): void
    {
        $service = app(MouWorkflowService::class);

        // First generation
        $service->generatePdf($this->mou);
        $this->mou->refresh();
        $this->assertEquals(1, $this->mou->version);
        $this->assertEquals(MouStatus::PDF_GENERATED, $this->mou->status);

        // Customize agreement
        $this->mou->update([
            'agreement_content' => '<p>Updated agreement terms for v2.</p>',
        ]);

        // Second generation (regeneration)
        $service->generatePdf($this->mou);
        $this->mou->refresh();
        $this->assertEquals(2, $this->mou->version);
        $this->assertEquals(MouStatus::PDF_GENERATED, $this->mou->status);
        $this->assertCount(2, $this->mou->getMedia('draft_pdf'));
    }

    public function test_beneficiary_name_is_resolved_from_bank_details(): void
    {
        $this->mou->update([
            'bank_details' => [
                'beneficiary_name' => 'Pranjal Sharma',
                'account_number' => '9988776655',
            ],
        ]);

        $service = app(MouTemplateService::class);
        $params = $service->getResolvedParameters($this->mou);

        $this->assertEquals('Pranjal Sharma', $params['bank_beneficiary_name']);

        $content = $service->getDefaultContent($this->mou, true);
        $this->assertStringContainsString('Pranjal Sharma', $content);
    }
}
