<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceClientQuote;
use App\Domain\Maintenance\Models\MaintenanceClientQuoteItem;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Maintenance\Models\MaintenanceVendorQuote;
use Illuminate\Support\Facades\DB;

class MaintenanceQuotationService
{
    /**
     * Add or update a vendor work order / trade quotation for a ticket.
     */
    public function addVendorQuote(MaintenanceRequest $request, array $data): MaintenanceVendorQuote
    {
        return DB::transaction(function () use ($request, $data) {
            $quote = MaintenanceVendorQuote::create([
                'maintenance_request_id' => $request->id,
                'vendor_party_id' => $data['vendor_party_id'],
                'vendor_trade_id' => $data['vendor_trade_id'] ?? null,
                'trade_title' => $data['trade_title'] ?? null,
                'scope_of_work' => $data['scope_of_work'] ?? null,
                'quoted_cost' => (float) ($data['quoted_cost'] ?? 0.00),
                'final_cost' => isset($data['final_cost']) ? (float) $data['final_cost'] : null,
                'status' => $data['status'] ?? 'quote_received',
                'notes' => $data['notes'] ?? null,
                'assigned_at' => now(),
            ]);

            $request->syncQuotationTotals();

            if (in_array($request->status, [MaintenanceStatus::DRAFT, MaintenanceStatus::SUBMITTED])) {
                $request->update(['status' => MaintenanceStatus::VENDOR_ASSIGNED]);
            }

            return $quote;
        });
    }

    /**
     * Create or update Dwelly's formal client quotation to the Owner / Tenant.
     */
    public function createOrUpdateClientQuote(
        MaintenanceRequest $request,
        array $itemsData,
        array $splitAmounts = []
    ): MaintenanceClientQuote {
        return DB::transaction(function () use ($request, $itemsData, $splitAmounts) {
            $clientQuote = $request->currentClientQuote;

            if (!$clientQuote || $clientQuote->isApproved()) {
                $clientQuote = MaintenanceClientQuote::create([
                    'maintenance_request_id' => $request->id,
                    'status' => 'draft',
                ]);
                $request->update(['current_client_quote_id' => $clientQuote->id]);
            }

            // Remove existing items and rebuild
            $clientQuote->items()->delete();

            $totalAmount = 0.0;
            foreach ($itemsData as $idx => $item) {
                $qty = (float) ($item['quantity'] ?? 1);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $lineTotal = (float) ($item['total_price'] ?? ($qty * $unitPrice));
                $totalAmount += $lineTotal;

                MaintenanceClientQuoteItem::create([
                    'maintenance_client_quote_id' => $clientQuote->id,
                    'vendor_quote_id' => $item['vendor_quote_id'] ?? null,
                    'description' => $item['description'] ?? 'Repair Service',
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'total_price' => $lineTotal,
                    'sort_order' => $idx + 1,
                ]);
            }

            $ownerAmount = isset($splitAmounts['owner_amount']) ? (float) $splitAmounts['owner_amount'] : 0.0;
            $tenantAmount = isset($splitAmounts['tenant_amount']) ? (float) $splitAmounts['tenant_amount'] : 0.0;
            $dwellyAmount = isset($splitAmounts['dwelly_amount']) ? (float) $splitAmounts['dwelly_amount'] : 0.0;

            if (empty($splitAmounts) || ($ownerAmount + $tenantAmount + $dwellyAmount == 0)) {
                // Auto calculate based on payer_type
                $payer = $request->payer_type?->value ?? (string) $request->payer_type;
                if ($payer === 'owner') {
                    $ownerAmount = $totalAmount;
                } elseif ($payer === 'tenant') {
                    $tenantAmount = $totalAmount;
                } elseif ($payer === 'dwelly') {
                    $dwellyAmount = $totalAmount;
                }
            }

            $clientQuote->update([
                'total_amount' => $totalAmount,
                'owner_amount' => $ownerAmount,
                'tenant_amount' => $tenantAmount,
                'dwelly_amount' => $dwellyAmount,
                'status' => 'pending_approval',
            ]);

            $request->update([
                'quotation_amount' => $totalAmount,
                'quotation_status' => 'pending',
                'status' => MaintenanceStatus::QUOTATION_PENDING,
            ]);

            $request->syncQuotationTotals();

            return $clientQuote;
        });
    }

    /**
     * Approve Dwelly's client quotation with mandatory proof.
     */
    public function approveClientQuote(
        MaintenanceClientQuote $quote,
        string $approvalNotes
    ): MaintenanceRequest {
        return DB::transaction(function () use ($quote, $approvalNotes) {
            $quote->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approval_notes' => $approvalNotes,
            ]);

            $request = $quote->maintenanceRequest;
            $request->update([
                'quotation_status' => 'approved',
                'quotation_approved_at' => now(),
                'quotation_approval_notes' => $approvalNotes,
                'status' => MaintenanceStatus::QUOTATION_APPROVED,
            ]);

            $request->syncQuotationTotals();

            return $request;
        });
    }

    /**
     * Reject client quotation and handle fallback action (e.g. revert to Direct Repair).
     */
    public function rejectClientQuote(
        MaintenanceClientQuote $quote,
        string $rejectionReason,
        string $action = 'revert_to_direct'
    ): MaintenanceRequest {
        return DB::transaction(function () use ($quote, $rejectionReason, $action) {
            $quote->update([
                'status' => 'rejected',
                'rejection_reason' => $rejectionReason,
                'rejection_action' => $action,
            ]);

            $request = $quote->maintenanceRequest;

            if ($action === 'revert_to_direct') {
                $request->update([
                    'is_direct_vendor' => true,
                    'quotation_status' => 'rejected',
                    'status' => MaintenanceStatus::IN_PROGRESS,
                    'is_dwelly_involved' => false,
                    'direct_payment_notes' => "Reverted to Direct Repair: Client declined Dwelly quotation ({$rejectionReason}). Owner/Tenant will coordinate directly.",
                ]);
            } else {
                $request->update([
                    'quotation_status' => 'rejected',
                    'status' => MaintenanceStatus::CANCELLED,
                ]);
            }

            $request->syncQuotationTotals();

            return $request;
        });
    }
}
