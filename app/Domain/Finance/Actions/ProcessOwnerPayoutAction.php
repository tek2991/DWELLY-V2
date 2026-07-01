<?php

namespace App\Domain\Finance\Actions;

use App\Domain\Finance\Models\OwnerPayout;
use App\Domain\Property\Models\Property;
use App\Domain\Finance\Services\CalculationService;
use App\Domain\Finance\Services\AccountingBridgeService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Brick\Money\Money;
use Illuminate\Support\Carbon;

class ProcessOwnerPayoutAction
{
    public function __construct(
        private AccountingBridgeService $accounting
    ) {}

    public function execute(Property $property, string $periodStart, string $periodEnd, User $initiator): OwnerPayout
    {
        return DB::transaction(function () use ($property, $periodStart, $periodEnd, $initiator) {
            
            // In a real application, we would aggregate all 'paid' RentPayments within the period
            // For this implementation, we will mock the rent collected based on the active tenancy
            $activeAgreement = $property->agreements()->where('status', 'active')->first();
            $rentCollected = $activeAgreement ? $activeAgreement->rent_amount : 0;
            
            // Assuming 10% management fee configuration
            $managementFeePercent = 10.00; 
            
            $rentCollectedMoney = Money::of($rentCollected, 'INR');
            $feeAmount = CalculationService::managementFee($rentCollectedMoney, $managementFeePercent);
            
            // Assuming a fixed reserve deduction of 500 for maintenance buffers
            $reserveDeduction = Money::of(500, 'INR');

            $payoutAmount = $rentCollectedMoney->minus($feeAmount)->minus($reserveDeduction);

            // If negative, no payout
            if ($payoutAmount->isNegative()) {
                $payoutAmount = Money::of(0, 'INR');
            }

            // Find the owner party from property configuration or default
            // In Dwelly, properties are linked to owners via `property_assignments` or directly.
            // For simplicity, we just fetch a random owner or assume the primary party is attached to the property.
            // Let's assume the property model has a generic owner relationship in the final schema, 
            // but for now we'll fetch an owner from the system.
            $owner = \App\Domain\Party\Models\Party::where('party_type', 'individual')->first();
            if (!$owner) {
                throw new \Exception("No owner found to process payout.");
            }

            $payout = OwnerPayout::create([
                'owner_id' => $owner->id,
                'property_id' => $property->id,
                'rent_collected' => $rentCollectedMoney->getAmount(),
                'management_fee' => $feeAmount->getAmount(),
                'reserve_deduction' => $reserveDeduction->getAmount(),
                'amount' => $payoutAmount->getAmount(),
                'status' => 'pending',
                'period_start' => Carbon::parse($periodStart),
                'period_end' => Carbon::parse($periodEnd),
            ]);

            // Sync with accounting ledger
            $this->accounting->recordOwnerPayout($payout);

            return $payout;
        });
    }
}
