<?php

namespace Tests\Feature\Audit;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Enums\ItemCondition;
use App\Domain\Audit\Enums\ItemStatus;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditCategory;
use App\Domain\Audit\Models\AuditItem;
use App\Domain\Property\Models\Property;
use App\Livewire\Operations\AuditInspectionComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AuditInspectionMobileUsabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('media');
    }

    public function test_item_filter_can_be_updated()
    {
        $inspector = User::factory()->create();
        $this->actingAs($inspector);

        $property = Property::create([
            'building_name' => 'Palm Heights 201',
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

        $item1 = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Refrigerator',
            'status' => ItemStatus::PENDING,
        ]);

        $item2 = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Microwave Oven',
            'status' => ItemStatus::INSPECTED,
            'condition' => ItemCondition::GOOD,
        ]);

        $component = Livewire::test(AuditInspectionComponent::class, ['audit' => $audit])
            ->assertSee('Refrigerator')
            ->assertSee('Microwave Oven')
            ->call('setItemFilter', 'pending')
            ->assertSee('Refrigerator')
            ->assertDontSee('Microwave Oven')
            ->call('setItemFilter', 'inspected')
            ->assertDontSee('Refrigerator')
            ->assertSee('Microwave Oven')
            ->call('setItemFilter', 'all')
            ->assertSee('Refrigerator')
            ->assertSee('Microwave Oven');
    }

    public function test_save_and_next_action_persists_condition_and_advances()
    {
        $inspector = User::factory()->create();
        $this->actingAs($inspector);

        $property = Property::create([
            'building_name' => 'Palm Heights 202',
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
            'name' => 'Master Bedroom',
            'sort_order' => 1,
        ]);

        $item1 = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Ceiling Fan',
            'status' => ItemStatus::PENDING,
        ]);

        $item2 = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Wardrobe Mirror',
            'status' => ItemStatus::PENDING,
        ]);

        $component = Livewire::test(AuditInspectionComponent::class, ['audit' => $audit])
            ->mountAction('editItem', ['item_id' => $item1->id])
            ->setActionData([
                'condition' => ItemCondition::EXCELLENT->value,
                'remarks' => 'Clean and functioning smoothly',
            ])
            ->call('saveAndNextItem');

        $item1->refresh();

        $this->assertEquals(ItemCondition::EXCELLENT, $item1->condition);
        $this->assertEquals('Clean and functioning smoothly', $item1->remarks);
        $this->assertEquals(ItemStatus::INSPECTED, $item1->status);

        // Verify next item was mounted automatically
        $this->assertEquals((string)$item2->id, $component->get('currentItemId'));

        // Test navigating back to previous item auto-saves current state
        $component->setActionData([
            'condition' => ItemCondition::GOOD->value,
            'remarks' => 'Looks fine',
        ])->call('navigatePreviousItem');

        $item2->refresh();
        $this->assertEquals(ItemCondition::GOOD, $item2->condition);
        $this->assertEquals('Looks fine', $item2->remarks);
        $this->assertEquals((string)$item1->id, $component->get('currentItemId'));
    }

    public function test_camera_upload_triggers_evidence_creation_and_auto_saves()
    {
        $inspector = User::factory()->create();
        $this->actingAs($inspector);

        $property = Property::create([
            'building_name' => 'Palm Heights 203',
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
            'name' => 'Balcony',
            'sort_order' => 1,
        ]);

        $item = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Railing Paint',
            'status' => ItemStatus::PENDING,
        ]);

        $file = UploadedFile::fake()->image('railing.jpg');

        $component = Livewire::test(AuditInspectionComponent::class, ['audit' => $audit])
            ->mountAction('editItem', ['item_id' => $item->id])
            ->setActionData([
                'condition' => ItemCondition::POOR->value,
                'remarks' => 'Paint peeling and rust visible on left bracket',
            ])
            ->set('uploads', [$file]);

        $item->refresh();

        $this->assertEquals(ItemCondition::POOR, $item->condition);
        $this->assertEquals('Paint peeling and rust visible on left bracket', $item->remarks);
        $this->assertEquals(ItemStatus::INSPECTED, $item->status);
        $this->assertCount(1, $item->evidence);
    }
}
