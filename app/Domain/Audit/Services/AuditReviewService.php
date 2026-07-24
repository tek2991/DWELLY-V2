<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\ItemStatus;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class AuditReviewService
{
    /**
     * Submit an audit for review.
     */
    public function submitForReview(Audit $audit)
    {
        // Increment round if it's a resubmission (already submitted previously)
        $isResubmission = $audit->submitted_at !== null;
        $newRound = $isResubmission ? $audit->review_round + 1 : 1;

        $audit->update([
            'status' => AuditStatus::PENDING_REVIEW,
            'submitted_at' => now(),
            'review_round' => $newRound,
        ]);

        activity()
            ->performedOn($audit)
            ->log('Workflow: Audit submitted');
    }

    /**
     * Approve a single audit item.
     */
    public function approveItem(AuditItem $item, User $reviewer)
    {
        DB::transaction(function () use ($item, $reviewer) {
            $item->update(['status' => ItemStatus::APPROVED]);

            $item->reviews()->create([
                'reviewer_id' => $reviewer->id,
                'review_round' => $item->category->audit->review_round,
                'status' => 'approved',
                'reviewed_at' => now(),
            ]);

            $this->evaluateWorkflowState($item->category->audit);
        });
        
        activity()
            ->performedOn($item)
            ->log('Review: ' . $item->name . ' approved');
    }

    /**
     * Reject a single audit item.
     */
    public function rejectItem(AuditItem $item, User $reviewer, string $reason, string $commentType)
    {
        DB::transaction(function () use ($item, $reviewer, $reason, $commentType) {
            $item->update(['status' => ItemStatus::REJECTED]);

            $item->reviews()->create([
                'reviewer_id' => $reviewer->id,
                'review_round' => $item->category->audit->review_round,
                'comment_type' => $commentType,
                'status' => 'rejected',
                'comments' => $reason,
                'reviewed_at' => now(),
            ]);

            $this->evaluateWorkflowState($item->category->audit);
        });

        activity()
            ->performedOn($item)
            ->log('Review: ' . $item->name . ' rejected');
    }

    /**
     * Request changes from the inspector.
     */
    public function requestChanges(Audit $audit)
    {
        $audit->update([
            'status' => AuditStatus::PARTIALLY_APPROVED,
        ]);

        activity()
            ->performedOn($audit)
            ->log('Workflow: Changes requested');
    }

    /**
     * Re-evaluate the workflow state based on item statuses.
     * This is the ONLY method that should transition the Audit status automatically during review.
     */
    public function evaluateWorkflowState(Audit $audit)
    {
        $items = $audit->items()->get();

        if ($items->isEmpty()) {
            return;
        }

        $totalItems = $items->count();
        $approvedItems = $items->where('status', ItemStatus::APPROVED)->count();
        $rejectedItems = $items->where('status', ItemStatus::REJECTED)->count();

        // Check if we need to start the review
        if ($audit->status === AuditStatus::PENDING_REVIEW && ($approvedItems > 0 || $rejectedItems > 0)) {
            $audit->update([
                'status' => AuditStatus::IN_REVIEW,
                'review_started_at' => now(),
            ]);
        }

        // If all items are approved, auto-approve the audit and sync items to property
        if ($approvedItems === $totalItems) {
            $audit->update([
                'status' => AuditStatus::APPROVED,
                'approved_at' => now(),
                'approved_by_id' => auth()->id() ?? $audit->reviewer_id,
            ]);

            $this->syncApprovedItemsToProperty($audit);

            activity()
                ->performedOn($audit)
                ->log('Workflow: Audit approved');
        }
    }

    /**
     * Accept all pending or non-approved items in an audit at once.
     */
    public function acceptAllItems(Audit $audit, User $reviewer): void
    {
        DB::transaction(function () use ($audit, $reviewer) {
            $items = $audit->items()->get();

            foreach ($items as $item) {
                if ($item->status !== ItemStatus::APPROVED) {
                    $item->update(['status' => ItemStatus::APPROVED]);

                    $item->reviews()->create([
                        'reviewer_id' => $reviewer->id,
                        'review_round' => $audit->review_round ?? 1,
                        'status' => 'approved',
                        'reviewed_at' => now(),
                    ]);
                }
            }

            $this->evaluateWorkflowState($audit);
        });

        activity()
            ->performedOn($audit)
            ->log('Review: All items approved by reviewer');
    }

    /**
     * Sync approved items added/staged during audit inspection into property models.
     */
    public function syncApprovedItemsToProperty(Audit $audit): void
    {
        DB::transaction(function () use ($audit) {
            $approvedItems = $audit->items()
                ->where('status', ItemStatus::APPROVED)
                ->get();

            $roomMap = [];

            // 1. Sync Staged Rooms
            foreach ($approvedItems as $item) {
                $snapshot = $item->snapshot_data ?? [];
                if (!empty($snapshot['is_new']) && ($snapshot['staged_type'] ?? null) === 'room') {
                    $roomDefId = $snapshot['room_definition_id'] ?? null;
                    $displayName = $snapshot['display_name'] ?? $item->name;

                    $newRoom = \App\Domain\Property\Models\PropertyRoom::create([
                        'property_id' => $audit->property_id,
                        'room_definition_id' => $roomDefId,
                        'custom_name' => $displayName,
                        'is_active' => true,
                    ]);

                    $roomMap[$item->id] = $newRoom->id;

                    unset($snapshot['is_new']);
                    $item->update([
                        'source_type' => \App\Domain\Property\Models\PropertyRoom::class,
                        'source_id' => $newRoom->id,
                        'snapshot_data' => $snapshot,
                    ]);
                }
            }

            // 2. Sync Staged Inventory
            foreach ($approvedItems as $item) {
                $snapshot = $item->snapshot_data ?? [];
                if (!empty($snapshot['is_new']) && ($snapshot['staged_type'] ?? null) === 'inventory') {
                    $invTypeId = $snapshot['inventory_type_id'] ?? null;
                    $count = $snapshot['count'] ?? 1;

                    $roomId = $snapshot['property_room_id'] ?? null;
                    $stagedRoomItemId = $snapshot['staged_room_item_id'] ?? null;
                    if (!$roomId && $stagedRoomItemId && isset($roomMap[$stagedRoomItemId])) {
                        $roomId = $roomMap[$stagedRoomItemId];
                    }

                    $newInv = \App\Domain\Property\Models\PropertyInventory::create([
                        'property_id' => $audit->property_id,
                        'property_room_id' => $roomId,
                        'inventory_type_id' => $invTypeId,
                        'count' => $count,
                    ]);

                    unset($snapshot['is_new']);
                    $item->update([
                        'source_type' => \App\Domain\Property\Models\PropertyInventory::class,
                        'source_id' => $newInv->id,
                        'snapshot_data' => $snapshot,
                    ]);
                }
            }

            // 3. Sync Staged Utilities
            foreach ($approvedItems as $item) {
                $snapshot = $item->snapshot_data ?? [];
                if (!empty($snapshot['is_new']) && ($snapshot['staged_type'] ?? null) === 'utility') {
                    $utilityTypeId = $snapshot['utility_type_id'] ?? null;
                    $paidBy = $snapshot['paid_by'] ?? 'owner';

                    $newUtil = \App\Domain\Property\Models\PropertyUtility::create([
                        'property_id' => $audit->property_id,
                        'utility_type_id' => $utilityTypeId,
                        'paid_by' => $paidBy,
                    ]);

                    unset($snapshot['is_new']);
                    $item->update([
                        'source_type' => \App\Domain\Property\Models\PropertyUtility::class,
                        'source_id' => $newUtil->id,
                        'snapshot_data' => $snapshot,
                    ]);
                }
            }
        });
    }
}
