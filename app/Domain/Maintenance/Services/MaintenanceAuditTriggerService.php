<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Enums\ItemStatus;
use App\Domain\Audit\Enums\EvidenceStatus;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Models\AuditCategory;
use App\Domain\Audit\Models\AuditEvidence;
use App\Domain\Audit\Models\AuditItem;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Property\Models\PropertyInventory;
use App\Domain\Property\Models\PropertyRoom;
use App\Domain\Property\Models\PropertyUtility;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class MaintenanceAuditTriggerService
{
    public function validateForAuditTrigger(MaintenanceRequest $request): array
    {
        $errors = [];

        // 1. Ticket Overview
        if (empty($request->title)) {
            $errors[] = 'Issue Title is missing on Ticket Overview.';
        }
        if (empty($request->description)) {
            $errors[] = 'Detailed Problem Description is missing on Ticket Overview.';
        }
        if (empty($request->assigned_inspector_id)) {
            $errors[] = 'Assigned Inspector / Executive is missing on Ticket Overview.';
        }
        if (empty($request->payer_type)) {
            $errors[] = 'Payer Decision (Who pays for repairs) is required.';
        }

        // 2. Dwelly Facilitated vs Direct Vendor checks
        if (!$request->is_direct_vendor) {
            if ($request->vendorQuotes()->count() === 0 && empty($request->vendor_party_id)) {
                $errors[] = 'At least one Service Vendor must be assigned for Dwelly-facilitated repairs.';
            }
            if ($request->quotation_status !== 'approved') {
                $errors[] = 'Quotation must be uploaded and approved before triggering the verification audit.';
            }
            $hasProof = $request->getMedia('quotation_approval_proofs')->isNotEmpty() || 
                ($request->currentClientQuote && $request->currentClientQuote->getMedia('approval_proof_files')->isNotEmpty());
            if (!$hasProof) {
                $errors[] = 'Quotation approval proof document/image upload is missing.';
            }
        }

        // 3. Repaired Items & Photos
        $request->loadMissing('items');
        $validItems = $request->items->filter(fn ($item) => !empty($item->itemable_type) && !empty($item->itemable_id));

        if ($validItems->isEmpty()) {
            $errors[] = 'At least 1 valid item (with Category and Specific Item selected) is required under Repair Items.';
        } else {
            foreach ($validItems->values() as $index => $item) {
                $rowNum = $index + 1;
                if (empty($item->issue_description)) {
                    $errors[] = "Item #{$rowNum}: Specific Defect / Issue description is missing.";
                }
                if (empty($item->repair_action)) {
                    $errors[] = "Item #{$rowNum}: Action Required is missing.";
                }
                if ($item->getMedia('issue_photos')->isEmpty()) {
                    $errors[] = "Item #{$rowNum}: At least 1 Defect Photo / Video (Before Repair) upload is required.";
                }
            }
        }

        return $errors;
    }

    public function triggerAudit(MaintenanceRequest $request, ?User $inspector = null): Audit
    {
        return DB::transaction(function () use ($request, $inspector) {
            // Create Audit
            $audit = Audit::create([
                'property_id' => $request->property_id,
                'tenant_id' => $request->tenant_id,
                'audit_type' => AuditType::MAINTENANCE,
                'status' => AuditStatus::DRAFT,
                'inspector_id' => $inspector?->id ?? $request->assigned_inspector_id ?? auth()->id(),
                'notes' => "Maintenance Verification Audit for Ticket #{$request->ticket_number}: {$request->title}",
            ]);

            // Group maintenance request items by standard categories (Rooms, Inventory, Utilities)
            $request->loadMissing('items.itemable');

            $categoryMap = [];

            foreach ($request->items as $index => $mItem) {
                if (empty($mItem->itemable_type) || empty($mItem->itemable_id)) {
                    continue;
                }

                $categoryName = $this->resolveCategoryName($mItem->itemable);
                $sortOrder = $this->resolveCategorySortOrder($categoryName);

                if (!isset($categoryMap[$categoryName])) {
                    $categoryMap[$categoryName] = AuditCategory::create([
                        'audit_id' => $audit->id,
                        'name' => $categoryName,
                        'sort_order' => $sortOrder,
                    ]);
                }

                $category = $categoryMap[$categoryName];
                $itemName = $this->resolveItemName($mItem->itemable);

                $auditItem = AuditItem::create([
                    'audit_category_id' => $category->id,
                    'name' => $itemName,
                    'source_type' => $mItem->itemable_type,
                    'source_id' => $mItem->itemable_id,
                    'status' => ItemStatus::PENDING,
                    'remarks' => "Defect: " . ($mItem->issue_description ?? 'N/A') . " | Action: " . ($mItem->repair_action ?? 'Repaired'),
                    'snapshot_data' => [
                        'maintenance_request_id' => $request->id,
                        'ticket_number' => $request->ticket_number,
                        'issue_description' => $mItem->issue_description,
                        'repair_action' => $mItem->repair_action,
                        'actual_cost' => $mItem->actual_cost,
                    ],
                    'sort_order' => $index + 1,
                ]);

                $displayOrder = 1;

                foreach ($mItem->getMedia('issue_photos') as $media) {
                    $media->copy($auditItem, 'evidence');

                    $evidence = AuditEvidence::create([
                        'audit_item_id' => $auditItem->id,
                        'status' => EvidenceStatus::PENDING,
                        'display_order' => $displayOrder++,
                    ]);
                    $media->copy($evidence, 'images');
                }

                foreach ($mItem->getMedia('repaired_photos') as $media) {
                    $media->copy($auditItem, 'evidence');

                    $evidence = AuditEvidence::create([
                        'audit_item_id' => $auditItem->id,
                        'status' => EvidenceStatus::PENDING,
                        'display_order' => $displayOrder++,
                    ]);
                    $media->copy($evidence, 'images');
                }
            }

            // Update Maintenance Request with reference to triggered audit
            $request->update([
                'triggered_audit_id' => $audit->id,
                'status' => MaintenanceStatus::AUDIT_PENDING,
            ]);

            return $audit;
        });
    }

    protected function resolveCategoryName(?object $itemable): string
    {
        if ($itemable instanceof PropertyRoom) {
            return 'Rooms';
        }
        if ($itemable instanceof PropertyInventory) {
            return 'Inventory';
        }
        if ($itemable instanceof PropertyUtility) {
            return 'Utilities';
        }

        return 'Maintenance Items';
    }

    protected function resolveCategorySortOrder(string $categoryName): int
    {
        return match ($categoryName) {
            'Rooms' => 10,
            'Inventory' => 20,
            'Utilities' => 30,
            default => 40,
        };
    }

    protected function resolveItemName(?object $itemable): string
    {
        if (!$itemable) {
            return 'Repaired Item';
        }

        if ($itemable instanceof PropertyRoom) {
            $roomName = $itemable->custom_name ?: ($itemable->roomDefinition?->name ?? 'Room');
            return $roomName;
        }

        if ($itemable instanceof PropertyInventory) {
            $invName = $itemable->inventoryType?->name ?? 'Inventory Item';
            if ($itemable->room) {
                $roomName = $itemable->room->custom_name ?: ($itemable->room->roomDefinition?->name ?? 'Room');
                return "{$invName} ({$roomName})";
            }
            return $invName;
        }

        if ($itemable instanceof PropertyUtility) {
            return $itemable->utilityType?->name ?? 'Utility';
        }

        return class_basename($itemable);
    }
}
