<?php

namespace Tek2991\Accounting\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Tek2991\Accounting\ValueObjects\Money;

/**
 * Eloquent cast that stores Money as integer minor units in the database
 * and hydrates it as a Money value object.
 *
 * Usage in model:
 *   protected $casts = [
 *       'amount' => MoneyCast::class . ':currency_code',
 *   ];
 *
 * The parameter after the colon is the column name that holds the currency code.
 * If omitted, defaults to the application's default currency.
 */
class MoneyCast implements CastsAttributes
{
    public function __construct(
        protected ?string $currencyColumn = null
    ) {}

    /**
     * Cast the stored integer value to a Money value object.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currencyCode = $this->resolveCurrencyCode($model, $attributes);

        return new Money((int) $value, $currencyCode);
    }

    /**
     * Prepare the Money value for storage as an integer.
     *
     * @param Model $model
     * @param string $key
     * @param mixed $value
     * @param array<string, mixed> $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->getAmount();
        }

        // Accept raw integer (already in minor units)
        if (is_int($value)) {
            return $value;
        }

        // Accept string/float as major units (e.g., "125.50")
        if (is_string($value) || is_float($value)) {
            $currencyCode = $this->resolveCurrencyCode($model, $attributes);

            return Money::fromDecimal((string) $value, $currencyCode)->getAmount();
        }

        return (int) $value;
    }

    /**
     * Resolve the currency code from the model or config.
     */
    protected function resolveCurrencyCode(Model $model, array $attributes): string
    {
        if ($this->currencyColumn && isset($attributes[$this->currencyColumn])) {
            return $attributes[$this->currencyColumn];
        }

        if ($this->currencyColumn && isset($model->{$this->currencyColumn})) {
            return $model->{$this->currencyColumn};
        }

        return config('accounting.default_currency', 'USD');
    }
}
