<?php

namespace App\Domain\Agreement\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Services\AuditReviewService;
use App\Domain\Audit\Services\AuditSnapshotService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TenancyDeboardingService
{
    /**
     * Initiate deboarding for an active tenancy agreement.
     */
    public function initiateDeboarding(TenancyAgreement $agreement, array $data): TenancyAgreement
    {
        return DB::transaction(function () use ($agreement, $data) {
            $agreement->update([
                'status' => 'deboarding_initiated',
                'notice_date' => $data['notice_date'] ?? now()->toDateString(),
                'vacating_date' => $data['vacating_date'] ?? null,
                'deboarding_reason' => $data['deboarding_reason'] ?? 'Agreement Expiry',
                'deboarding_notes' => $data['deboarding_notes'] ?? null,
            ]);

            activity()
                ->performedOn($agreement)
                ->log("Deboarding initiated for Tenancy #{$agreement->code}");

            return $agreement;
        });
    }

    /**
     * Trigger a Move-Out Verification Audit for the tenancy agreement.
     */
    public function triggerMoveOutAudit(TenancyAgreement $agreement, ?User $inspector = null): Audit
    {
        return DB::transaction(function () use ($agreement, $inspector) {
            $audit = Audit::create([
                'property_id' => $agreement->property_id,
                'tenant_id' => $agreement->primaryTenant?->party_id,
                'audit_type' => AuditType::MOVE_OUT,
                'reference_audit_id' => $agreement->audit_id, // Reference Move-In Baseline
                'status' => AuditStatus::DRAFT,
                'inspector_id' => $inspector?->id ?? auth()->id(),
                'notes' => "Move-Out Exit Audit for Tenancy Agreement #{$agreement->code}",
            ]);

            // Generate full snapshot of property inventory/rooms for move-out verification
            app(AuditSnapshotService::class)->generateSnapshot($audit);

            $agreement->update([
                'move_out_audit_id' => $audit->id,
            ]);

            activity()
                ->performedOn($agreement)
                ->log("Move-Out Verification Audit #{$audit->audit_number} triggered for Tenancy #{$agreement->code}");

            return $audit;
        });
    }

    /**
     * Complete deboarding, permanently lock move-out audit, and vacate property.
     */
    public function completeDeboardingAndVacate(
        TenancyAgreement $agreement,
        string $newPropertyStatus = 'vacant',
        ?array $settlementData = null,
        ?User $actor = null
    ): TenancyAgreement {
        return DB::transaction(function () use ($agreement, $newPropertyStatus, $settlementData, $actor) {
            // Lock linked Move-Out Audit if present
            if ($agreement->moveOutAudit) {
                app(AuditReviewService::class)->lockAudit($agreement->moveOutAudit, $actor);
            }

            // Lock linked Move-In Baseline Audit if present
            if ($agreement->audit) {
                app(AuditReviewService::class)->lockAudit($agreement->audit, $actor);
            }

            $updateData = [
                'status' => 'vacated',
                'deboarded_at' => now(),
                'keys_returned' => true,
                'keys_returned_at' => $agreement->keys_returned_at ?? now(),
            ];

            if ($settlementData) {
                $updateData['deposit_deductions_breakdown'] = $settlementData['breakdown'] ?? null;
                $updateData['net_deposit_refund'] = $settlementData['net_refund'] ?? 0.00;
                $updateData['deposit_settlement_status'] = $settlementData['settlement_status'] ?? 'settled';
            }

            $agreement->update($updateData);

            // Transition property status
            $property = $agreement->property;
            if ($property) {
                $property->update([
                    'status' => $newPropertyStatus,
                ]);
            }

            activity()
                ->performedOn($agreement)
                ->log("Deboarding completed. Tenancy #{$agreement->code} marked as vacated, property marked as {$newPropertyStatus}.");

            return $agreement;
        });
    }
}
