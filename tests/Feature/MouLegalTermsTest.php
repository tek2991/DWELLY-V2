<?php

namespace Tests\Feature;

use App\Domain\Mou\Services\MouService;
use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Opportunity\Models\FinancialModel;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Property\Models\UtilityProvider;
use App\Domain\Property\Models\UtilityType;
use App\Filament\Resources\Operations\MOUResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MouLegalTermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_mou_snapshots_financial_model_details_from_opportunity()
    {
        $user = User::factory()->create();

        $financialModel = FinancialModel::create([
            'slug' => 'annual-subscription',
            'name' => 'Annual subscription',
            'description' => "One month's rent",
            'fee_collection' => 'Charged at agreement signing or renewal',
            'is_active' => true,
        ]);

        $opportunity = Opportunity::create([
            'number' => 'OPP-600',
            'title' => 'Test Opportunity Legal Terms',
            'owner_name' => 'Test Owner',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'expected_financial_model_id' => $financialModel->id,
            'expected_rent' => 15000,
            'status' => OpportunityStatus::READY_FOR_MOU,
        ]);

        $service = app(MouService::class);
        $mou = $service->createDraftFromOpportunity($opportunity);

        $this->assertEquals($financialModel->id, $mou->legal_terms['financial_model_id']);
        $this->assertEquals('Annual subscription', $mou->legal_terms['financial_model_name']);
        $this->assertEquals("One month's rent", $mou->legal_terms['financial_model_description']);
        $this->assertEquals('Charged at agreement signing or renewal', $mou->legal_terms['financial_model_fee_collection']);
    }

    public function test_creating_mou_snapshots_financial_model_and_utility_provider_names()
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Business Owner', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('Business Owner');
        $this->actingAs($user);

        $financialModel = FinancialModel::create([
            'slug' => 'rent-share',
            'name' => 'Rent share',
            'description' => 'Monthly % of rent',
            'fee_collection' => 'Auto-deducted from monthly owner payout',
            'is_active' => true,
        ]);

        $utilityType = UtilityType::create([
            'name' => 'Electricity',
            'slug' => 'electricity',
            'category' => 'electricity',
        ]);

        $provider = UtilityProvider::create([
            'utility_type_id' => $utilityType->id,
            'slug' => 'apdcl',
            'name' => 'Assam Power Distribution Company Limited (APDCL)',
            'is_active' => true,
        ]);

        $opportunity = Opportunity::create([
            'number' => 'OPP-601',
            'title' => 'Test Opportunity 601',
            'owner_name' => 'Test Owner',
            'owner_phone' => '9876543210',
            'assigned_user_id' => $user->id,
            'status' => OpportunityStatus::READY_FOR_MOU,
        ]);

        $mou = \App\Domain\Mou\Models\Mou::create([
            'number' => 'MOU-TEST-001',
            'opportunity_id' => $opportunity->id,
            'status' => \App\Domain\Opportunity\Enums\MouStatus::DRAFT,
            'start_date' => now()->format('Y-m-d'),
            'legal_terms' => [
                'address' => 'Sample Address 123',
                'rent_amount' => 12000,
                'financial_model_id' => $financialModel->id,
                'electricity_provider_id' => $provider->id,
                'electricity_consumer_id' => '123456789',
                'pricing_model' => 'Old Pricing',
                'fee_percentage' => 10,
            ],
        ]);

        $file = \Illuminate\Http\UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        Livewire::test(MOUResource\Pages\EditMOU::class, [
            'record' => $mou->getKey(),
        ])
            ->fillForm([
                'mou_attachments' => [$file],
                'bank_details' => [
                    'bank_name' => 'HDFC Bank',
                    'beneficiary_name' => 'Test Owner',
                    'account_number' => '1234567890',
                    'ifsc_code' => 'HDFC0001234',
                    'bank_address' => 'Guwahati Branch',
                ],
                'legal_terms' => [
                    'address' => 'Sample Address 123 Updated',
                    'rent_amount' => 12000,
                    'financial_model_id' => $financialModel->id,
                    'electricity_provider_id' => $provider->id,
                    'electricity_consumer_id' => '123456789',
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $mou->refresh();
        $this->assertEquals('Rent share', $mou->legal_terms['financial_model_name']);
        $this->assertEquals('Monthly % of rent', $mou->legal_terms['financial_model_description']);
        $this->assertEquals('Auto-deducted from monthly owner payout', $mou->legal_terms['financial_model_fee_collection']);
        $this->assertEquals('Assam Power Distribution Company Limited (APDCL)', $mou->legal_terms['electricity_provider_name']);
        
        $this->assertArrayNotHasKey('pricing_model', $mou->legal_terms);
        $this->assertArrayNotHasKey('fee_percentage', $mou->legal_terms);
    }
}
