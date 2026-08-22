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

            $this->evaluateWorkflowState($item->category->audit, true);
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

            $this->evaluateWorkflowState($item->category->audit, true);
        });

        activity()
            ->performedOn($item)
            ->log('Review: ' . $item->name . ' rejected');
    }

    /**
     * Reset a review decision on an item back to inspected status.
     */
    public function resetItem(AuditItem $item, User $reviewer)
    {
        DB::transaction(function () use ($item, $reviewer) {
            $item->update(['status' => ItemStatus::INSPECTED]);

            $item->reviews()->create([
                'reviewer_id' => $reviewer->id,
                'review_round' => $item->category->audit->review_round,
                'status' => 'pending',
                'comments' => 'Review status reset to inspected',
                'reviewed_at' => now(),
            ]);

            $this->evaluateWorkflowState($item->category->audit, true);
        });

        activity()
            ->performedOn($item)
            ->log('Review: ' . $item->name . ' reset to inspected');
    }

    /**
     * Approve the overall property layout video.
     */
    public function approveVideo(Audit $audit, User $reviewer): void
    {
        DB::transaction(function () use ($audit, $reviewer) {
            $audit->update([
                'video_status' => 'approved',
                'video_reviewed_at' => now(),
                'video_reviewed_by_id' => $reviewer->id,
                'video_rejection_reason' => null,
                'video_rejection_type' => null,
            ]);

            $this->evaluateWorkflowState($audit, true);
        });

        activity()
            ->performedOn($audit)
            ->by($reviewer)
            ->log('Review: Overall layout video approved');
    }

    /**
     * Reject the overall property layout video.
     */
    public function rejectVideo(Audit $audit, User $reviewer, string $reason, string $commentType): void
    {
        DB::transaction(function () use ($audit, $reviewer, $reason, $commentType) {
            $audit->update([
                'video_status' => 'rejected',
                'video_reviewed_at' => now(),
                'video_reviewed_by_id' => $reviewer->id,
                'video_rejection_reason' => $reason,
                'video_rejection_type' => $commentType,
            ]);

            $this->evaluateWorkflowState($audit, true);
        });

        activity()
            ->performedOn($audit)
            ->by($reviewer)
            ->log('Review: Overall layout video rejected');
    }

    /**
     * Reset the review decision on overall property layout video.
     */
    public function resetVideo(Audit $audit, User $reviewer): void
    {
        DB::transaction(function () use ($audit, $reviewer) {
            $audit->update([
                'video_status' => 'pending',
                'video_reviewed_at' => null,
                'video_reviewed_by_id' => null,
                'video_rejection_reason' => null,
                'video_rejection_type' => null,
            ]);

            $this->evaluateWorkflowState($audit, true);
        });

        activity()
            ->performedOn($audit)
            ->by($reviewer)
            ->log('Review: Overall layout video reset to pending');
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
     * Re-evaluate the workflow state based on item and video statuses.
     * This method handles review start and demotion of approved audits if items/video change.
     */
    public function evaluateWorkflowState(Audit $audit, bool $fromUserAction = false)
    {
        $items = $audit->items()->get();

        if ($items->isEmpty()) {
            return;
        }

        $totalItems = $items->count();
        $approvedItems = $items->where('status', ItemStatus::APPROVED)->count();
        $rejectedItems = $items->where('status', ItemStatus::REJECTED)->count();

        $requiresVideo = $audit->audit_type !== \App\Domain\Audit\Enums\AuditType::MAINTENANCE;
        $hasVideo = $audit->getFirstMedia('layout_video') !== null;
        $videoApproved = $hasVideo ? ($audit->video_status === 'approved') : !$requiresVideo;
        $videoRejected = $hasVideo && ($audit->video_status === 'rejected');

        // Check if we need to start the review or transition from partially approved back to in_review
        if ($audit->status === AuditStatus::PENDING_REVIEW && ($approvedItems > 0 || $rejectedItems > 0 || in_array($audit->video_status, ['approved', 'rejected']))) {
            $audit->update([
                'status' => AuditStatus::IN_REVIEW,
                'review_started_at' => now(),
            ]);
        } elseif ($audit->status === AuditStatus::PARTIALLY_APPROVED && $rejectedItems === 0 && !$videoRejected) {
            $audit->update([
                'status' => AuditStatus::IN_REVIEW,
            ]);
        }

        // If audit was previously APPROVED but items or video are no longer all approved
        if ($audit->status === AuditStatus::APPROVED && ($approvedItems < $totalItems || !$videoApproved)) {
            $newStatus = ($rejectedItems > 0 || $videoRejected) ? AuditStatus::PARTIALLY_APPROVED : AuditStatus::IN_REVIEW;
            $audit->update([
                'status' => $newStatus,
                'approved_at' => null,
                'approved_by_id' => null,
            ]);

            activity()
                ->performedOn($audit)
                ->log("Workflow: Audit status demoted from Approved to {$newStatus->getLabel()} due to item or video decision change");
        }
    }

    /**
     * Explicitly approve an audit after all items and layout video are approved.
     */
    public function approveAudit(Audit $audit, User $reviewer): void
    {
        if (!$audit->canApprove()) {
            throw new \Exception("Cannot approve audit. Ensure all items and layout video are approved.");
        }

        DB::transaction(function () use ($audit, $reviewer) {
            $audit->update([
                'status' => AuditStatus::APPROVED,
                'approved_at' => now(),
                'approved_by_id' => $reviewer->id,
            ]);

            $this->syncApprovedItemsToProperty($audit);
        });

        activity()
            ->performedOn($audit)
            ->by($reviewer)
            ->log('Workflow: Audit approved');
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

            if ($audit->getFirstMedia('layout_video') !== null && $audit->video_status !== 'approved') {
                $audit->update([
                    'video_status' => 'approved',
                    'video_reviewed_at' => now(),
                    'video_reviewed_by_id' => $reviewer->id,
                    'video_rejection_reason' => null,
                    'video_rejection_type' => null,
                ]);
            }

            $this->evaluateWorkflowState($audit, true);
        });

        activity()
            ->performedOn($audit)
            ->by($reviewer)
            ->log('Review: All items and layout video approved by reviewer');
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
                if (!empty($snapshot['is_new']) && empty($snapshot['exclude_from_sync']) && ($snapshot['staged_type'] ?? null) === 'room') {
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
                if (!empty($snapshot['is_new']) && empty($snapshot['exclude_from_sync']) && ($snapshot['staged_type'] ?? null) === 'inventory') {
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
                if (!empty($snapshot['is_new']) && empty($snapshot['exclude_from_sync']) && ($snapshot['staged_type'] ?? null) === 'utility') {
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

    /**
     * Reopen an approved or completed audit (unless locked).
     */
    public function reopenAudit(Audit $audit, ?User $actor = null): void
    {
        if ($audit->is_locked) {
            throw new \Exception("Cannot reopen audit. The audit is permanently locked.");
        }

        $auditStatusValue = $audit->status instanceof AuditStatus ? $audit->status->value : (string)$audit->status;
        if (!in_array($auditStatusValue, ['approved', 'completed'])) {
            throw new \Exception("Only approved or completed audits can be reopened.");
        }

        $audit->update([
            'status' => AuditStatus::IN_REVIEW,
            'approved_at' => null,
            'approved_by_id' => null,
        ]);

        activity()
            ->performedOn($audit)
            ->by($actor ?? auth()->user())
            ->log('Workflow: Audit reopened from approved state');
    }

    /**
     * Permanently lock an audit.
     */
    public function lockAudit(Audit $audit, ?User $actor = null): void
    {
        if ($audit->is_locked) {
            return;
        }

        $audit->update([
            'is_locked' => true,
            'status' => AuditStatus::COMPLETED,
            'locked_at' => now(),
            'locked_by_id' => $actor?->id ?? auth()->id(),
        ]);

        activity()
            ->performedOn($audit)
            ->by($actor ?? auth()->user())
            ->log('Workflow: Audit permanently locked');
    }
}
