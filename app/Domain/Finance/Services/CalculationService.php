<?php

namespace App\Domain\Finance\Services;

use Brick\Money\Money;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class CalculationService
{
    private const CURRENCY = 'INR';

    /**
     * Get the number of days in a specific month.
     */
    public static function rentDaysInMonth(Carbon $date): int
    {
        return $date->daysInMonth;
    }

    /**
     * Calculate pro-rata rent for partial months.
     */
    public static function proRataRent(Money $monthlyRent, int $daysActive, int $daysInMonth): Money
    {
        if ($daysActive >= $daysInMonth) {
            return $monthlyRent;
        }

        // Formula: (Monthly Rent / Days in Month) * Days Active
        return $monthlyRent
            ->dividedBy($daysInMonth, RoundingMode::HALF_UP)
            ->multipliedBy($daysActive, RoundingMode::HALF_UP);
    }

    /**
     * Calculate the management fee.
     */
    public static function managementFee(Money $rent, float $feePercentage): Money
    {
        return $rent->multipliedBy($feePercentage / 100, RoundingMode::HALF_UP);
    }

    /**
     * Calculate net owner payout.
     */
    public static function netOwnerPayout(Money $rentCollected, Money $managementFee, ?Money $deductions = null): Money
    {
        $deductions = $deductions ?? Money::of(0, self::CURRENCY);
        
        return $rentCollected
            ->minus($managementFee)
            ->minus($deductions);
    }

    /**
     * Calculate security deposit balance.
     * In a real implementation, this would query the SecurityDepositEntry ledger.
     */
    public static function sdBalance(string $tenancyId, Collection $ledgerEntries): Money
    {
        $balance = Money::of(0, self::CURRENCY);
        
        foreach ($ledgerEntries as $entry) {
            if ($entry->type === 'credit') {
                $balance = $balance->plus(Money::of($entry->amount, self::CURRENCY));
            } else {
                $balance = $balance->minus(Money::of($entry->amount, self::CURRENCY));
            }
        }
        
        return $balance;
    }

    /**
     * Calculate Dwelly's markup margin on vendor quotes.
     */
    public static function dwellyMargin(Money $vendorQuote, float $markupPercentage): Money
    {
        return $vendorQuote->multipliedBy($markupPercentage / 100, RoundingMode::HALF_UP);
    }

    /**
     * Split a utility bill equally among active tenants.
     */
    public static function utilityShare(Money $totalBill, int $activeTenantsCount): Money
    {
        if ($activeTenantsCount === 0) {
            return Money::of(0, self::CURRENCY);
        }
        
        return $totalBill->dividedBy($activeTenantsCount, RoundingMode::HALF_UP);
    }
}
