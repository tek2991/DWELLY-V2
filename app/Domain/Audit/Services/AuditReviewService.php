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

        // If all items are approved, auto-approve the audit
        if ($approvedItems === $totalItems) {
            $audit->update([
                'status' => AuditStatus::APPROVED,
                'approved_at' => now(),
                'approved_by_id' => auth()->id() ?? $audit->reviewer_id,
            ]);

            activity()
                ->performedOn($audit)
                ->log('Workflow: Audit approved');
        }
    }
}
