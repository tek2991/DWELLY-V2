<?php

namespace Tests\Feature\Audit;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Property\Models\Property;
use App\Filament\Resources\Operations\AuditResource\Pages\EditAudit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditEditValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_perform_inspection_button_is_disabled_without_inspector()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Sunset Villa',
            'status' => 'vacant',
        ]);

        $auditWithoutInspector = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::DRAFT,
            'inspector_id' => null,
            'reviewer_id' => $user->id,
        ]);

        Livewire::test(EditAudit::class, ['record' => $auditWithoutInspector->getKey()])
            ->assertActionDisabled('inspect');
    }

    public function test_perform_inspection_button_is_enabled_with_inspector()
    {
        $user = User::factory()->create();
        $inspector = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Sunset Villa',
            'status' => 'vacant',
        ]);

        $auditWithInspector = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::DRAFT,
            'inspector_id' => $inspector->id,
            'reviewer_id' => $user->id,
        ]);

        Livewire::test(EditAudit::class, ['record' => $auditWithInspector->getKey()])
            ->assertActionEnabled('inspect');
    }

    public function test_assigned_reviewer_is_mandatory_on_audit_edit()
    {
        $user = User::factory()->create();
        $inspector = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Sunset Villa',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::DRAFT,
            'inspector_id' => $inspector->id,
            'reviewer_id' => $user->id,
        ]);

        Livewire::test(EditAudit::class, ['record' => $audit->getKey()])
            ->fillForm([
                'reviewer_id' => null,
            ])
            ->call('save')
            ->assertHasFormErrors(['reviewer_id' => 'required']);
    }
}
