<?php

namespace Tek2991\Accounting\ValueObjects;

use Brick\Money\Money as BrickMoney;
use Brick\Money\Context\DefaultContext;
use Brick\Math\RoundingMode;

/**
 * Immutable Money value object wrapping Brick\Money for precision-critical
 * accounting math. All amounts are stored as minor units (cents) in the
 * database and operated on without floating-point rounding errors.
 */
final class Money
{
    private BrickMoney $money;

    /**
     * @param int|string $amount Amount in minor units (cents). Accepts string for large values.
     * @param string $currencyCode ISO 4217 currency code.
     */
    public function __construct(int|string $amount, string $currencyCode = 'USD')
    {
        $this->money = BrickMoney::ofMinor($amount, $currencyCode);
    }

    /**
     * Create from a major-unit decimal string (e.g., "125.50").
     */
    public static function fromDecimal(string $amount, string $currencyCode = 'USD'): self
    {
        $brick = BrickMoney::of($amount, $currencyCode, new DefaultContext(), RoundingMode::HALF_UP);

        return new self($brick->getMinorAmount()->toInt(), $currencyCode);
    }

    /**
     * Create a zero-value Money for the given currency.
     */
    public static function zero(string $currencyCode = 'USD'): self
    {
        return new self(0, $currencyCode);
    }

    /**
     * Get the amount in minor units (cents).
     */
    public function getAmount(): int
    {
        return $this->money->getMinorAmount()->toInt();
    }

    /**
     * Get the amount as a decimal string (e.g., "125.50").
     */
    public function getDecimal(): string
    {
        return $this->money->getAmount()->__toString();
    }

    /**
     * Get the ISO 4217 currency code.
     */
    public function getCurrencyCode(): string
    {
        return $this->money->getCurrency()->getCurrencyCode();
    }

    /**
     * Format the amount for display using intl (e.g., "$1,250.00").
     */
    public function format(?string $locale = null): string
    {
        $locale = $locale ?? app()->getLocale();
        $formatter = new \NumberFormatter($locale, \NumberFormatter::CURRENCY);

        return $formatter->formatCurrency(
            (float) $this->getDecimal(),
            $this->getCurrencyCode()
        );
    }

    /**
     * Add another Money value.
     */
    public function add(self $other): self
    {
        $result = $this->money->plus($other->money, RoundingMode::HALF_UP);

        return new self($result->getMinorAmount()->toInt(), $this->getCurrencyCode());
    }

    /**
     * Subtract another Money value.
     */
    public function subtract(self $other): self
    {
        $result = $this->money->minus($other->money, RoundingMode::HALF_UP);

        return new self($result->getMinorAmount()->toInt(), $this->getCurrencyCode());
    }

    /**
     * Multiply by a factor (e.g., for exchange rates or quantities).
     */
    public function multiply(int|float|string $factor): self
    {
        $result = $this->money->multipliedBy($factor, RoundingMode::HALF_UP);

        return new self($result->getMinorAmount()->toInt(), $this->getCurrencyCode());
    }

    /**
     * Check if this amount is zero.
     */
    public function isZero(): bool
    {
        return $this->money->isZero();
    }

    /**
     * Check if this amount is positive.
     */
    public function isPositive(): bool
    {
        return $this->money->isPositive();
    }

    /**
     * Check if this amount is negative.
     */
    public function isNegative(): bool
    {
        return $this->money->isNegative();
    }

    /**
     * Get the absolute value.
     */
    public function absolute(): self
    {
        if ($this->isNegative()) {
            return $this->multiply(-1);
        }

        return $this;
    }

    /**
     * Get the negated value.
     */
    public function negate(): self
    {
        return $this->multiply(-1);
    }

    /**
     * Compare with another Money value.
     * Returns -1, 0, or 1.
     */
    public function compareTo(self $other): int
    {
        return $this->money->compareTo($other->money);
    }

    /**
     * Check equality with another Money value.
     */
    public function equals(self $other): bool
    {
        return $this->money->isEqualTo($other->money);
    }

    /**
     * Check if this amount is greater than another.
     */
    public function greaterThan(self $other): bool
    {
        return $this->money->isGreaterThan($other->money);
    }

    /**
     * Check if this amount is less than another.
     */
    public function lessThan(self $other): bool
    {
        return $this->money->isLessThan($other->money);
    }

    /**
     * Get the underlying Brick\Money instance.
     */
    public function toBrickMoney(): BrickMoney
    {
        return $this->money;
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
