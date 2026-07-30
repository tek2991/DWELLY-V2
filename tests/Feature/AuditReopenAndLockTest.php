<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Services\AuditReviewService;
use App\Domain\Property\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

class AuditReopenAndLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_reopen_an_approved_unlocked_audit()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Test Apartment',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => \App\Domain\Audit\Enums\AuditType::MOVE_IN,
            'status' => AuditStatus::APPROVED,
            'is_locked' => false,
            'approved_at' => now(),
            'approved_by_id' => $user->id,
        ]);

        $this->assertTrue($audit->canReopen());

        $service = app(AuditReviewService::class);
        $service->reopenAudit($audit, $user);

        $audit->refresh();
        $this->assertEquals(AuditStatus::IN_REVIEW, $audit->status);
        $this->assertNull($audit->approved_at);
    }

    public function test_cannot_reopen_a_permanently_locked_audit()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Locked Apartment',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => \App\Domain\Audit\Enums\AuditType::MOVE_IN,
            'status' => AuditStatus::APPROVED,
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by_id' => $user->id,
        ]);

        $this->assertFalse($audit->canReopen());

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cannot reopen audit. The audit is permanently locked.');

        $service = app(AuditReviewService::class);
        $service->reopenAudit($audit, $user);
    }

    public function test_locking_an_audit_prevents_subsequent_updates()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Lock Test',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => \App\Domain\Audit\Enums\AuditType::MOVE_IN,
            'status' => AuditStatus::APPROVED,
            'is_locked' => false,
        ]);

        $service = app(AuditReviewService::class);
        $service->lockAudit($audit, $user);

        $audit->refresh();
        $this->assertTrue($audit->is_locked);
        $this->assertNotNull($audit->locked_at);

        // Attempting to update a locked model returns false / fails
        $result = $audit->update(['notes' => 'Attempting update']);
        $this->assertFalse($result);
    }

    public function test_can_change_status_of_approved_and_rejected_items_during_review()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Review Item Test',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => \App\Domain\Audit\Enums\AuditType::MOVE_IN,
            'status' => AuditStatus::IN_REVIEW,
            'is_locked' => false,
        ]);

        $category = $audit->categories()->create([
            'name' => 'General',
            'sort_order' => 1,
        ]);

        $item = $category->items()->create([
            'name' => 'Fan',
            'status' => \App\Domain\Audit\Enums\ItemStatus::APPROVED,
        ]);

        $service = app(AuditReviewService::class);

        // Reject an already approved item
        $service->rejectItem($item, $user, 'Fan speed regulator broken', 'CONDITION');
        $item->refresh();
        $this->assertEquals(\App\Domain\Audit\Enums\ItemStatus::REJECTED, $item->status);

        // Approve a rejected item
        $service->approveItem($item, $user);
        $item->refresh();
        $this->assertEquals(\App\Domain\Audit\Enums\ItemStatus::APPROVED, $item->status);

        // Reset item decision to inspected
        $service->resetItem($item, $user);
        $item->refresh();
        $this->assertEquals(\App\Domain\Audit\Enums\ItemStatus::INSPECTED, $item->status);
    }

    public function test_inspector_can_add_items_when_audit_is_partially_approved()
    {
        $inspector = User::factory()->create();
        $this->actingAs($inspector);

        $property = Property::create([
            'building_name' => 'Partially Approved Test',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => \App\Domain\Audit\Enums\AuditType::MOVE_IN,
            'status' => AuditStatus::PARTIALLY_APPROVED,
            'inspector_id' => $inspector->id,
            'is_locked' => false,
        ]);

        $category = $audit->categories()->create([
            'name' => 'Living Room',
            'sort_order' => 1,
        ]);

        $newItem = $category->items()->create([
            'name' => 'Extra Curtain Rod',
            'status' => \App\Domain\Audit\Enums\ItemStatus::INSPECTED,
            'condition' => \App\Domain\Audit\Enums\ItemCondition::GOOD,
        ]);

        $this->assertTrue($newItem->isEditable());
    }

    public function test_excluded_items_are_not_synced_to_property()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $property = Property::create([
            'building_name' => 'Sync Exclusion Test',
            'status' => 'vacant',
        ]);

        $audit = Audit::create([
            'property_id' => $property->id,
            'audit_type' => \App\Domain\Audit\Enums\AuditType::MOVE_IN,
            'status' => AuditStatus::IN_REVIEW,
            'is_locked' => false,
        ]);

        $category = $audit->categories()->create([
            'name' => 'Rooms',
            'sort_order' => 1,
        ]);

        $item = $category->items()->create([
            'name' => 'Temporary Storage Spot',
            'status' => \App\Domain\Audit\Enums\ItemStatus::APPROVED,
            'snapshot_data' => [
                'is_new' => true,
                'staged_type' => 'room',
                'display_name' => 'Temporary Storage Spot',
                'exclude_from_sync' => true,
            ],
        ]);

        $service = app(AuditReviewService::class);
        $service->syncApprovedItemsToProperty($audit);

        $this->assertDatabaseMissing('property_rooms', [
            'custom_name' => 'Temporary Storage Spot',
        ]);
    }
}
