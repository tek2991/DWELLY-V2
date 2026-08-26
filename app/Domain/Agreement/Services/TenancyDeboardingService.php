<?php

namespace App\Domain\Agreement\Services;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Models\TenantDeboarding;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Services\AuditReviewService;
use App\Domain\Audit\Services\AuditSnapshotService;
use App\Domain\Finance\Services\SecurityDepositService;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceRequestItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tek2991\Accounting\Enums\InvoiceStatus;
use Tek2991\Accounting\Models\Invoice;

class TenancyDeboardingService
{
    /**
     * Initiate deboarding for an active tenancy agreement and create the dedicated TenantDeboarding workflow record.
     */
    public function initiateDeboarding(TenancyAgreement $agreement, array $data, ?User $actor = null): TenantDeboarding
    {
        return DB::transaction(function () use ($agreement, $data, $actor) {
            $actorId = $actor?->id ?? auth()->id();
            $tenantId = $agreement->primaryTenant?->party_id 
                ?? $agreement->roles()->where('is_primary', true)->first()?->party_id;

            $noticeDate = $data['notice_date'] ?? now()->toDateString();
            $vacatingDate = $data['vacating_date'] ?? $data['target_vacating_date'] ?? now()->addDays(30)->toDateString();
            $reason = $data['deboarding_reason'] ?? $data['reason'] ?? 'Agreement Expiry';
            $notes = $data['deboarding_notes'] ?? $data['notes'] ?? null;

            // Update Tenancy Agreement
            $agreement->update([
                'status' => 'deboarding_initiated',
                'notice_date' => $noticeDate,
                'vacating_date' => $vacatingDate,
                'deboarding_reason' => $reason,
                'deboarding_notes' => $notes,
            ]);

            // Find or create TenantDeboarding
            $deboarding = TenantDeboarding::firstOrNew(['tenancy_agreement_id' => $agreement->id]);
            $deboarding->fill([
                'property_id' => $agreement->property_id,
                'tenant_id' => $tenantId,
                'status' => DeboardingStatus::AUDIT_PENDING,
                'notice_date' => $noticeDate,
                'target_vacating_date' => $vacatingDate,
                'reason' => $reason,
                'notes' => $notes,
                'security_deposit_held' => $agreement->security_deposit ?? 0.00,
            ]);
            $deboarding->save();

            // Auto-trigger Move-Out Audit if not yet created
            if (! $agreement->move_out_audit_id && ! $deboarding->move_out_audit_id) {
                $audit = $this->triggerMoveOutAudit($agreement, $actor);
                $deboarding->update([
                    'move_out_audit_id' => $audit->id,
                ]);
            } else {
                $deboarding->update([
                    'move_out_audit_id' => $agreement->move_out_audit_id,
                ]);
            }

            // Recalculate settlement baseline
            $this->calculateSettlement($deboarding);

            activity()
                ->performedOn($deboarding)
                ->causedBy($actorId ? User::find($actorId) : null)
                ->log("Deboarding #{$deboarding->code} initiated for Tenancy #{$agreement->code}");

            return $deboarding;
        });
    }

    /**
     * Trigger a Move-Out Verification Audit for the tenancy agreement.
     */
    public function triggerMoveOutAudit(TenancyAgreement $agreement, ?User $inspector = null): Audit
    {
        return DB::transaction(function () use ($agreement, $inspector) {
            $tenantId = $agreement->primaryTenant?->party_id 
                ?? $agreement->roles()->where('is_primary', true)->first()?->party_id;

            $audit = Audit::create([
                'property_id' => $agreement->property_id,
                'tenant_id' => $tenantId,
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

            if ($agreement->deboarding) {
                $agreement->deboarding->update([
                    'move_out_audit_id' => $audit->id,
                    'status' => DeboardingStatus::AUDIT_PENDING,
                ]);
            }

            activity()
                ->performedOn($agreement)
                ->log("Move-Out Verification Audit #{$audit->audit_number} triggered for Tenancy #{$agreement->code}");

            return $audit;
        });
    }

    /**
     * Create a linked Maintenance Request for damages identified during deboarding.
     */
    public function createMaintenanceForDeboarding(
        TenantDeboarding $deboarding,
        array $data,
        ?User $actor = null
    ): MaintenanceRequest {
        return DB::transaction(function () use ($deboarding, $data, $actor) {
            $property = $deboarding->property;
            $agreement = $deboarding->tenancyAgreement;

            $priority = $data['priority'] ?? MaintenancePriority::MEDIUM;
            $payerType = $data['payer_type'] ?? PayerType::TENANT;
            $estimatedCost = (float) ($data['estimated_cost'] ?? $data['total_cost'] ?? 0.00);
            $tenantAmount = (float) ($data['tenant_amount'] ?? ($payerType === PayerType::TENANT || $payerType === 'tenant' ? $estimatedCost : 0.00));
            $ownerAmount = (float) ($data['owner_amount'] ?? ($payerType === PayerType::OWNER || $payerType === 'owner' ? $estimatedCost : 0.00));

            $maintenance = MaintenanceRequest::create([
                'property_id' => $deboarding->property_id,
                'tenant_id' => $deboarding->tenant_id,
                'tenant_deboarding_id' => $deboarding->id,
                'owner_id' => $property?->owner_id ?? $property?->owner?->id,
                'vendor_party_id' => $data['vendor_party_id'] ?? null,
                'assigned_inspector_id' => $data['assigned_inspector_id'] ?? auth()->id(),
                'title' => $data['title'] ?? "Move-Out Repairs for {$property?->code}",
                'description' => $data['description'] ?? 'Repairs and damage resolution identified during Exit Deboarding Audit.',
                'priority' => $priority,
                'status' => MaintenanceStatus::SUBMITTED,
                'payer_type' => $payerType,
                'total_cost' => $estimatedCost,
                'tenant_amount' => $tenantAmount,
                'owner_amount' => $ownerAmount,
                'created_by_id' => $actor?->id ?? auth()->id(),
            ]);

            // Add items if provided
            if (! empty($data['items']) && is_array($data['items'])) {
                foreach ($data['items'] as $item) {
                    MaintenanceRequestItem::create([
                        'maintenance_request_id' => $maintenance->id,
                        'itemable_type' => $item['itemable_type'] ?? null,
                        'itemable_id' => $item['itemable_id'] ?? null,
                        'issue_description' => $item['issue_description'] ?? 'Exit damage repair',
                        'repair_action' => $item['repair_action'] ?? 'Repair / Replace',
                        'estimated_cost' => $item['estimated_cost'] ?? 0.00,
                    ]);
                }
            }

            // Update deboarding damage flags and recalculate
            $deboarding->damages_identified = true;
            $deboarding->status = DeboardingStatus::MAINTENANCE_REQUIRED;
            $deboarding->save();

            $this->calculateSettlement($deboarding);

            activity()
                ->performedOn($deboarding)
                ->log("Maintenance Request #{$maintenance->ticket_number} created for Deboarding #{$deboarding->code}");

            return $maintenance;
        });
    }

    /**
     * Calculate and sync security deposit settlement deductions for a deboarding record.
     */
    public function calculateSettlement(TenantDeboarding $deboarding): array
    {
        $agreement = $deboarding->tenancyAgreement;
        $depositHeld = (float) ($deboarding->security_deposit_held ?: ($agreement?->security_deposit ?? 0.00));

        // 1. Calculate unpaid rent invoices for this tenant
        $unpaidRent = 0.00;
        if ($deboarding->tenant_id) {
            $unpaidRent = (float) Invoice::where('party_id', $deboarding->tenant_id)
                ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::PartiallyPaid])
                ->get()
                ->sum(fn ($inv) => (float) ($inv->amount_due ?? $inv->balance ?? 0.00));
        }

        // 2. Calculate tenant's share of repairs from linked maintenance requests
        $maintenanceDeductions = (float) $deboarding->maintenanceRequests()->sum('tenant_amount');
        $totalRepairCost = (float) $deboarding->maintenanceRequests()->sum('total_cost');
        $ownerRepairCost = (float) $deboarding->maintenanceRequests()->sum('owner_amount');

        $deboarding->security_deposit_held = $depositHeld;
        $deboarding->total_repair_cost = $totalRepairCost;
        $deboarding->tenant_repair_share = $maintenanceDeductions;
        $deboarding->owner_repair_share = $ownerRepairCost;

        if ($unpaidRent > 0 && (float) $deboarding->unpaid_rent_deduction <= 0) {
            $deboarding->unpaid_rent_deduction = $unpaidRent;
        }

        if ($maintenanceDeductions > 0 && (float) $deboarding->maintenance_deduction <= 0) {
            $deboarding->maintenance_deduction = $maintenanceDeductions;
        }

        $totalDeductions = (float) $deboarding->unpaid_rent_deduction
            + (float) $deboarding->maintenance_deduction
            + (float) $deboarding->utility_deduction
            + (float) $deboarding->other_deductions;

        $deboarding->total_deductions = round($totalDeductions, 2);

        $netRefund = $depositHeld - $totalDeductions;
        if ($netRefund >= 0) {
            $deboarding->net_deposit_refund = round($netRefund, 2);
            $deboarding->excess_due_from_tenant = 0.00;
        } else {
            $deboarding->net_deposit_refund = 0.00;
            $deboarding->excess_due_from_tenant = round(abs($netRefund), 2);
        }

        $deboarding->save();

        return [
            'deposit_held' => $depositHeld,
            'unpaid_rent' => (float) $deboarding->unpaid_rent_deduction,
            'maintenance_deduction' => (float) $deboarding->maintenance_deduction,
            'utility_deduction' => (float) $deboarding->utility_deduction,
            'other_deductions' => (float) $deboarding->other_deductions,
            'total_deductions' => (float) $deboarding->total_deductions,
            'net_refund' => (float) $deboarding->net_deposit_refund,
            'excess_due' => (float) $deboarding->excess_due_from_tenant,
        ];
    }

    /**
     * Complete deboarding, permanently lock audits, settle deposit, and vacate property.
     * Supports passing either TenantDeboarding or TenancyAgreement for backward compatibility.
     */
    public function completeDeboardingAndVacate(
        TenantDeboarding|TenancyAgreement $target,
        string $newPropertyStatus = 'vacant',
        ?array $settlementData = null,
        ?User $actor = null
    ): TenantDeboarding|TenancyAgreement {
        return DB::transaction(function () use ($target, $newPropertyStatus, $settlementData, $actor) {
            if ($target instanceof TenancyAgreement) {
                $agreement = $target;
                $deboarding = $agreement->deboarding ?? TenantDeboarding::firstOrNew(['tenancy_agreement_id' => $agreement->id]);
            } else {
                $deboarding = $target;
                $agreement = $deboarding->tenancyAgreement;
            }

            $actorId = $actor?->id ?? auth()->id();
            $actorUser = $actorId ? User::find($actorId) : null;

            // Lock linked Move-Out Audit if present
            $moveOutAudit = $agreement->moveOutAudit ?? $deboarding->moveOutAudit;
            if ($moveOutAudit) {
                app(AuditReviewService::class)->lockAudit($moveOutAudit, $actorUser);
            }

            // Lock linked Move-In Baseline Audit if present
            if ($agreement->audit) {
                app(AuditReviewService::class)->lockAudit($agreement->audit, $actorUser);
            }

            $netRefund = $settlementData['net_refund'] ?? $deboarding->net_deposit_refund ?? 0.00;
            $settlementStatus = $settlementData['settlement_status'] ?? $deboarding->settlement_status ?? 'settled';

            // Optional: Record Accounting transaction if net refund / deductions were processed
            if (! empty($settlementData['record_accounting_transaction'])) {
                try {
                    $deductions = (float) ($deboarding->total_deductions ?? 0.00);
                    app(SecurityDepositService::class)->recordDepositSettlement(
                        $agreement,
                        $deductions,
                        null,
                        0.0,
                        (float) $netRefund,
                        now()->toDateString()
                    );
                } catch (\Throwable $e) {
                    logger()->warning("Accounting deposit settlement skipped: {$e->getMessage()}");
                }
            }

            // Update Deboarding Record
            $deboarding->fill([
                'status' => DeboardingStatus::COMPLETED,
                'keys_returned' => true,
                'keys_returned_at' => $deboarding->keys_returned_at ?? now(),
                'keys_received_by_id' => $deboarding->keys_received_by_id ?? $actorId,
                'actual_vacating_date' => $deboarding->actual_vacating_date ?? now()->toDateString(),
                'net_deposit_refund' => $netRefund,
                'settlement_status' => $settlementStatus,
                'refund_payment_mode' => $settlementData['refund_payment_mode'] ?? $deboarding->refund_payment_mode,
                'refund_transaction_reference' => $settlementData['refund_transaction_reference'] ?? $deboarding->refund_transaction_reference,
                'refunded_at' => $settlementData['refunded_at'] ?? $deboarding->refunded_at ?? ($settlementStatus === 'settled' || $settlementStatus === 'refunded' ? now() : null),
                'new_property_status' => $newPropertyStatus,
                'completed_at' => now(),
                'completed_by_id' => $actorId,
            ]);
            $deboarding->save();

            // Update Tenancy Agreement
            $agreementUpdate = [
                'status' => 'vacated',
                'deboarded_at' => now(),
                'keys_returned' => true,
                'keys_returned_at' => $deboarding->keys_returned_at ?? now(),
                'net_deposit_refund' => $netRefund,
                'deposit_settlement_status' => $settlementStatus,
            ];

            if ($settlementData && isset($settlementData['breakdown'])) {
                $agreementUpdate['deposit_deductions_breakdown'] = $settlementData['breakdown'];
            }

            $agreement->update($agreementUpdate);

            // Update Property Status
            $property = $agreement->property ?? $deboarding->property;
            if ($property) {
                $property->update([
                    'status' => $newPropertyStatus,
                ]);
            }

            activity()
                ->performedOn($deboarding)
                ->causedBy($actorUser)
                ->log("Deboarding #{$deboarding->code} completed. Tenancy #{$agreement->code} marked as vacated, property marked as {$newPropertyStatus}.");

            return $target;
        });
    }
}
