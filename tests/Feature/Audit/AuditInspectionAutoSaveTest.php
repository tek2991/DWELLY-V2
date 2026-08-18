<?php

namespace Tests\Feature\Audit;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Enums\ItemCondition;
use App\Domain\Audit\Enums\ItemStatus;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditCategory;
use App\Domain\Audit\Models\AuditEvidence;
use App\Domain\Audit\Models\AuditItem;
use App\Domain\Property\Models\Property;
use App\Livewire\Operations\AuditInspectionComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AuditInspectionAutoSaveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('media');
    }

    public function test_uploading_image_auto_saves_condition_and_remarks()
    {
        $inspector = User::factory()->create();
        $this->actingAs($inspector);

        $property = Property::create([
            'building_name' => 'Palm Grove 101',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::IN_PROGRESS,
            'inspector_id' => $inspector->id,
            'reviewer_id' => $inspector->id,
        ]);

        $category = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Living Room',
            'sort_order' => 1,
        ]);

        $item = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Main Door Lock',
            'status' => ItemStatus::PENDING,
            'condition' => null,
            'remarks' => null,
        ]);

        $file = UploadedFile::fake()->image('door_lock.jpg');

        $component = Livewire::test(AuditInspectionComponent::class, ['audit' => $audit])
            ->mountAction('editItem', ['item_id' => $item->id])
            ->setActionData([
                'condition' => ItemCondition::FAIR->value,
                'remarks' => 'Lock is stiff and requires lubrication',
            ])
            ->set('uploads', [$file]);

        $item->refresh();

        $this->assertEquals(ItemCondition::FAIR, $item->condition);
        $this->assertEquals('Lock is stiff and requires lubrication', $item->remarks);
        $this->assertEquals(ItemStatus::INSPECTED, $item->status);
        $this->assertCount(1, $item->evidence);
        $this->assertCount(1, $item->revisions);
        $this->assertEquals($inspector->id, $item->revisions->first()->updated_by_id);
    }

    public function test_open_editor_on_existing_photo_auto_saves_condition_and_remarks()
    {
        $inspector = User::factory()->create();
        $this->actingAs($inspector);

        $property = Property::create([
            'building_name' => 'Palm Grove 102',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::IN_PROGRESS,
            'inspector_id' => $inspector->id,
            'reviewer_id' => $inspector->id,
        ]);

        $category = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Bedroom',
            'sort_order' => 1,
        ]);

        $item = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Window Glass',
            'status' => ItemStatus::PENDING,
            'condition' => null,
            'remarks' => null,
        ]);

        $evidence = AuditEvidence::create([
            'audit_item_id' => $item->id,
            'status' => \App\Domain\Audit\Enums\EvidenceStatus::PENDING,
            'display_order' => 1,
        ]);
        $evidence->addMedia(UploadedFile::fake()->image('window.jpg'))->toMediaCollection('images');

        Livewire::test(AuditInspectionComponent::class, ['audit' => $audit])
            ->mountAction('editItem', ['item_id' => $item->id])
            ->setActionData([
                'condition' => ItemCondition::POOR->value,
                'remarks' => 'Crack on top right corner of window',
            ])
            ->call('openEditor', $evidence->id);

        $item->refresh();

        $this->assertEquals(ItemCondition::POOR, $item->condition);
        $this->assertEquals('Crack on top right corner of window', $item->remarks);
        $this->assertEquals(ItemStatus::INSPECTED, $item->status);
    }

    public function test_submitting_inspect_modal_saves_details()
    {
        $inspector = User::factory()->create();
        $this->actingAs($inspector);

        $property = Property::create([
            'building_name' => 'Palm Grove 103',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::IN_PROGRESS,
            'inspector_id' => $inspector->id,
            'reviewer_id' => $inspector->id,
        ]);

        $category = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Kitchen',
            'sort_order' => 1,
        ]);

        $item = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Sink Faucet',
            'status' => ItemStatus::PENDING,
        ]);

        Livewire::test(AuditInspectionComponent::class, ['audit' => $audit])
            ->mountAction('editItem', ['item_id' => $item->id])
            ->setActionData([
                'condition' => ItemCondition::GOOD->value,
                'remarks' => 'Faucet working properly without leakage',
            ])
            ->callMountedAction();

        $item->refresh();

        $this->assertEquals(ItemCondition::GOOD, $item->condition);
        $this->assertEquals('Faucet working properly without leakage', $item->remarks);
        $this->assertEquals(ItemStatus::INSPECTED, $item->status);
    }

    public function test_closing_editor_remounts_item_with_saved_data()
    {
        $inspector = User::factory()->create();
        $this->actingAs($inspector);

        $property = Property::create([
            'building_name' => 'Palm Grove 104',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::IN_PROGRESS,
            'inspector_id' => $inspector->id,
            'reviewer_id' => $inspector->id,
        ]);

        $category = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Bathroom',
            'sort_order' => 1,
        ]);

        $item = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Shower Head',
            'status' => ItemStatus::PENDING,
        ]);

        $file = UploadedFile::fake()->image('shower.jpg');

        $component = Livewire::test(AuditInspectionComponent::class, ['audit' => $audit])
            ->mountAction('editItem', ['item_id' => $item->id])
            ->setActionData([
                'condition' => ItemCondition::EXCELLENT->value,
                'remarks' => 'Brand new shower head installed',
            ])
            ->set('uploads', [$file]);

        // Simulating closing the editor after annotation
        $component->call('closeEditor')
            ->assertDispatched('mount-edit-item', itemId: (string) $item->id);

        $item->refresh();
        $this->assertEquals(ItemCondition::EXCELLENT, $item->condition);
        $this->assertEquals('Brand new shower head installed', $item->remarks);
    }
}
