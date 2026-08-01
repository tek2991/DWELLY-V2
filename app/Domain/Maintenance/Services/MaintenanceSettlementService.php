<?php

namespace App\Domain\Maintenance\Services;

use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use Illuminate\Support\Facades\DB;

class MaintenanceSettlementService
{
    public function settleMaintenanceRequest(
        MaintenanceRequest $request,
        PayerType $payerType,
        float $totalCost,
        float $vendorCost = 0.0,
        float $ownerAmount = 0.0,
        float $tenantAmount = 0.0,
        float $dwellyAmount = 0.0,
        ?string $directPaymentRef = null,
        ?string $directPaymentNotes = null
    ): MaintenanceRequest {
        return DB::transaction(function () use (
            $request,
            $payerType,
            $totalCost,
            $vendorCost,
            $ownerAmount,
            $tenantAmount,
            $dwellyAmount,
            $directPaymentRef,
            $directPaymentNotes
        ) {
            $isDwellyInvolved = $payerType->isDwellyInvoiced() || $payerType->isDwellyAbsorbed() || $dwellyAmount > 0;

            if ($payerType === PayerType::OWNER_DIRECT || $payerType === PayerType::DWELLY_INVOICE_OWNER) {
                $ownerAmount = $totalCost;
                $tenantAmount = 0.0;
                $dwellyAmount = 0.0;
            } elseif ($payerType === PayerType::TENANT_DIRECT || $payerType === PayerType::DWELLY_INVOICE_TENANT) {
                $tenantAmount = $totalCost;
                $ownerAmount = 0.0;
                $dwellyAmount = 0.0;
            } elseif ($payerType === PayerType::DWELLY_DIRECT_ABSORBED) {
                $dwellyAmount = $totalCost;
                $ownerAmount = 0.0;
                $tenantAmount = 0.0;
            }

            $request->update([
                'payer_type' => $payerType,
                'is_dwelly_involved' => $isDwellyInvolved,
                'total_cost' => $totalCost,
                'vendor_cost' => $vendorCost > 0 ? $vendorCost : $totalCost,
                'owner_amount' => $ownerAmount,
                'tenant_amount' => $tenantAmount,
                'dwelly_amount' => $dwellyAmount,
                'direct_payment_reference' => $directPaymentRef,
                'direct_payment_notes' => $directPaymentNotes,
                'status' => MaintenanceStatus::WORK_COMPLETED,
                'completed_at' => now(),
            ]);

            return $request;
        });
    }
}
