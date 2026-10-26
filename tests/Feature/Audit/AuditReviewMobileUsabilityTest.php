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
use App\Filament\Resources\Operations\AuditResource\Pages\ReviewAudit;
use App\Livewire\Operations\AuditReviewComponent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AuditReviewMobileUsabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('media');
    }

    public function test_review_item_filter_updates_and_filters_view(): void
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $this->actingAs($reviewer);

        $property = Property::create([
            'building_name' => 'Sunset Towers 402',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'audit_number' => 'AUD-TEST-001',
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::IN_REVIEW,
            'inspector_id' => $inspector->id,
            'reviewer_id' => $reviewer->id,
            'submitted_at' => now(),
        ]);

        $category = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Living Room',
            'sort_order' => 1,
        ]);

        $itemPending = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Sofa Set',
            'status' => ItemStatus::INSPECTED,
            'condition' => ItemCondition::GOOD,
        ]);

        $itemApproved = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Coffee Table',
            'status' => ItemStatus::APPROVED,
            'condition' => ItemCondition::EXCELLENT,
        ]);

        $itemRejected = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Ceiling Fan',
            'status' => ItemStatus::REJECTED,
            'condition' => ItemCondition::DAMAGED,
        ]);

        Livewire::test(AuditReviewComponent::class, ['audit' => $audit])
            ->assertSee('Sofa Set')
            ->assertSee('Coffee Table')
            ->assertSee('Ceiling Fan')
            ->call('setItemFilter', 'approved')
            ->assertSee('Coffee Table')
            ->assertDontSee('Sofa Set')
            ->assertDontSee('Ceiling Fan')
            ->call('setItemFilter', 'rejected')
            ->assertSee('Ceiling Fan')
            ->assertDontSee('Sofa Set')
            ->assertDontSee('Coffee Table')
            ->call('setItemFilter', 'pending')
            ->assertSee('Sofa Set')
            ->assertDontSee('Coffee Table')
            ->assertDontSee('Ceiling Fan')
            ->call('setItemFilter', 'all')
            ->assertSee('Sofa Set')
            ->assertSee('Coffee Table')
            ->assertSee('Ceiling Fan');
    }

    public function test_review_component_renders_mobile_sticky_bar_and_responsive_elements(): void
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $this->actingAs($reviewer);

        $property = Property::create([
            'building_name' => 'Marina View 101',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'audit_number' => 'AUD-TEST-002',
            'property_id' => $property->id,
            'audit_type' => AuditType::MAINTENANCE,
            'status' => AuditStatus::IN_REVIEW,
            'inspector_id' => $inspector->id,
            'reviewer_id' => $reviewer->id,
            'submitted_at' => now(),
        ]);

        $category = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Bathroom',
            'sort_order' => 1,
        ]);

        AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Water Heater',
            'status' => ItemStatus::INSPECTED,
            'condition' => ItemCondition::FAIR,
        ]);

        Livewire::test(AuditReviewComponent::class, ['audit' => $audit])
            ->assertSeeHtml('audit-mobile-sticky-bar')
            ->assertSeeHtml('audit-review-metrics-grid')
            ->assertSeeHtml('audit-item-actions')
            ->assertSeeHtml('evidence-modal-mobile-tabs');
    }

    public function test_reviewer_can_approve_item(): void
    {
        $inspector = User::factory()->create();
        $reviewer = User::factory()->create();
        $this->actingAs($reviewer);

        $property = Property::create([
            'building_name' => 'Palm Grove 501',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'audit_number' => 'AUD-TEST-003',
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::IN_REVIEW,
            'inspector_id' => $inspector->id,
            'reviewer_id' => $reviewer->id,
            'submitted_at' => now(),
        ]);

        $category = AuditCategory::create([
            'audit_id' => $audit->id,
            'name' => 'Bedroom',
            'sort_order' => 1,
        ]);

        $item = AuditItem::create([
            'audit_category_id' => $category->id,
            'name' => 'Air Conditioner',
            'status' => ItemStatus::INSPECTED,
            'condition' => ItemCondition::GOOD,
        ]);

        Livewire::test(AuditReviewComponent::class, ['audit' => $audit])
            ->callAction('approveItem', arguments: ['item_id' => $item->id]);

        $item->refresh();
        $this->assertEquals(ItemStatus::APPROVED, $item->status);
    }

    public function test_review_audit_page_title_displays_audit_number(): void
    {
        $property = Property::create([
            'code' => 'PROP-999',
            'building_name' => 'Crown Plaza 12',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'audit_number' => 'AUD-2026-9999',
            'property_id' => $property->id,
            'audit_type' => AuditType::MOVE_IN,
            'status' => AuditStatus::IN_REVIEW,
        ]);

        $page = new ReviewAudit();
        $page->record = $audit;

        $this->assertEquals('Review Audit: AUD-2026-9999', $page->getTitle());
    }
}
