<?php

namespace Tek2991\Accounting\Services\TaxRegimes;

use Illuminate\Support\Collection;
use Tek2991\Accounting\Contracts\TaxRegimeInterface;
use Tek2991\Accounting\Enums\TaxComponentType;
use Tek2991\Accounting\Enums\TaxType;
use Tek2991\Accounting\ValueObjects\TaxCalculationContext;

class IndiaGstTaxRegime implements TaxRegimeInterface
{
    public function calculate(TaxCalculationContext $context): Collection
    {
        $calculatedComponents = collect();
        
        $tax = $context->tax;
        $taxableAmount = $context->amount;
        $branchStateId = $context->branch->state_id;
        
        $posStateId = $context->document->place_of_supply_state_id ?? $context->contact?->state_id;

        $isIntrastate = $branchStateId && $posStateId && ($branchStateId === $posStateId);

        $components = $tax->components->filter(function ($component) use ($isIntrastate) {
            if ($isIntrastate) {
                return $component->type === TaxComponentType::Intrastate;
            } else {
                return $component->type === TaxComponentType::Interstate;
            }
        });

        foreach ($components as $component) {
            $rate = (float) $component->rate;
            $isInclusive = $context->modeOverride ? $context->modeOverride === 'inclusive' : $tax->type === TaxType::Inclusive;
            
            if ($isInclusive) {
                $totalRate = $components->sum('rate');
                $taxAmount = $taxableAmount * ($rate / (100 + $totalRate));
            } else {
                $taxAmount = $taxableAmount * ($rate / 100);
            }
            
            $taxAmount = (int) round($taxAmount);

            $isSales = get_class($context->document) === \Tek2991\Accounting\Models\Invoice::class;
            $accountId = $isSales ? $component->sales_account_id : $component->purchase_account_id;

            $calculatedComponents->push([
                'component_id' => $component->id,
                'name'         => $component->name,
                'rate'         => $rate,
                'account_id'   => $accountId,
                'amount'       => $taxAmount, // in minor units
            ]);
        }

        return $calculatedComponents;
    }
}
