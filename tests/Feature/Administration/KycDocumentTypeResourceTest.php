<?php

namespace Tests\Feature\Administration;

use App\Domain\Shared\Enums\KycRequirementLevel;
use App\Domain\Shared\Models\KycDocumentType;
use App\Filament\Resources\Administration\KycDocumentTypeResource;
use App\Filament\Resources\Administration\KycDocumentTypeResource\Pages\ManageKycDocumentTypes;
use App\Models\User;
use Database\Seeders\KycDocumentTypeSeeder;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KycDocumentTypeResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_render_kyc_document_types_management_page(): void
    {
        $user = User::factory()->create();
        $this->seed(KycDocumentTypeSeeder::class);

        Livewire::actingAs($user)
            ->test(ManageKycDocumentTypes::class)
            ->assertSuccessful()
            ->assertCanSeeTableRecords(KycDocumentType::all())
            ->assertSee('Aadhaar Card')
            ->assertSee('PAN Card')
            ->assertSee('Electricity Bill');
    }

    public function test_can_create_kyc_document_type(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageKycDocumentTypes::class)
            ->callAction(CreateAction::class, [
                'name' => 'Passport Document',
                'code' => 'passport_doc',
                'description' => 'Valid passport copy',
                'has_number' => true,
                'number_label' => 'Passport File / Book Number',
                'number_regex' => '^[A-Z0-9]{8,9}$',
                'mou_owner_requirement' => KycRequirementLevel::OPTIONAL->value,
                'mou_signatory_requirement' => KycRequirementLevel::MANDATORY->value,
                'tenancy_primary_tenant_requirement' => KycRequirementLevel::MANDATORY->value,
                'tenancy_secondary_tenant_requirement' => KycRequirementLevel::OPTIONAL->value,
                'is_active' => true,
                'sort_order' => 10,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('kyc_document_types', [
            'name' => 'Passport Document',
            'code' => 'passport_doc',
            'has_number' => true,
            'number_label' => 'Passport File / Book Number',
            'mou_owner_requirement' => 'optional',
            'mou_signatory_requirement' => 'mandatory',
        ]);
    }

    public function test_can_edit_kyc_document_type(): void
    {
        $user = User::factory()->create();

        $doc = KycDocumentType::create([
            'name' => 'Voter Card',
            'code' => 'voter_card',
            'has_number' => true,
            'number_label' => 'EPIC Number',
            'mou_owner_requirement' => KycRequirementLevel::DISABLED,
            'mou_signatory_requirement' => KycRequirementLevel::DISABLED,
            'tenancy_primary_tenant_requirement' => KycRequirementLevel::DISABLED,
            'tenancy_secondary_tenant_requirement' => KycRequirementLevel::DISABLED,
            'is_active' => true,
            'sort_order' => 20,
        ]);

        Livewire::actingAs($user)
            ->test(ManageKycDocumentTypes::class)
            ->callTableAction(EditAction::class, $doc, [
                'name' => 'Voter ID Card (EPIC)',
                'code' => 'voter_card',
                'has_number' => true,
                'number_label' => 'EPIC No.',
                'mou_owner_requirement' => KycRequirementLevel::MANDATORY->value,
                'mou_signatory_requirement' => KycRequirementLevel::OPTIONAL->value,
                'tenancy_primary_tenant_requirement' => KycRequirementLevel::MANDATORY->value,
                'tenancy_secondary_tenant_requirement' => KycRequirementLevel::OPTIONAL->value,
                'is_active' => true,
                'sort_order' => 20,
            ])
            ->assertHasNoTableActionErrors();

        $doc->refresh();
        $this->assertEquals('Voter ID Card (EPIC)', $doc->name);
        $this->assertEquals(KycRequirementLevel::MANDATORY, $doc->mou_owner_requirement);
        $this->assertEquals(KycRequirementLevel::OPTIONAL, $doc->mou_signatory_requirement);
    }

    public function test_can_select_and_save_document_icon(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageKycDocumentTypes::class)
            ->callAction(CreateAction::class, [
                'name' => 'Driving License Test',
                'code' => 'driving_license_test',
                'icon' => 'heroicon-m-truck',
                'has_number' => true,
                'number_label' => 'DL Number',
                'mou_owner_requirement' => KycRequirementLevel::OPTIONAL->value,
                'mou_signatory_requirement' => KycRequirementLevel::DISABLED->value,
                'is_active' => true,
                'sort_order' => 15,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('kyc_document_types', [
            'code' => 'driving_license_test',
            'icon' => 'heroicon-m-truck',
        ]);
    }

    public function test_document_code_is_locked_on_edit(): void
    {
        $user = User::factory()->create();

        $doc = KycDocumentType::create([
            'name' => 'Original Document',
            'code' => 'original_code',
            'mou_owner_requirement' => KycRequirementLevel::DISABLED,
            'mou_signatory_requirement' => KycRequirementLevel::DISABLED,
            'tenancy_primary_requirement' => KycRequirementLevel::DISABLED,
            'tenancy_secondary_requirement' => KycRequirementLevel::DISABLED,
            'is_active' => true,
        ]);

        Livewire::actingAs($user)
            ->test(ManageKycDocumentTypes::class)
            ->callTableAction(EditAction::class, $doc, [
                'name' => 'Updated Document Name',
                'code' => 'attempted_new_code',
                'mou_owner_requirement' => KycRequirementLevel::OPTIONAL->value,
                'mou_signatory_requirement' => KycRequirementLevel::DISABLED->value,
                'tenancy_primary_requirement' => KycRequirementLevel::DISABLED->value,
                'tenancy_secondary_requirement' => KycRequirementLevel::DISABLED->value,
                'is_active' => true,
            ])
            ->assertHasNoTableActionErrors();

        $doc->refresh();
        $this->assertEquals('Updated Document Name', $doc->name);
        $this->assertEquals('original_code', $doc->code);
    }
}
