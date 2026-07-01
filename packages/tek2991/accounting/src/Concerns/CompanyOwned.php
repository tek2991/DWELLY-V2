<?php

namespace Tek2991\Accounting\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tek2991\Accounting\Contracts\CompanyAccessor;

/**
 * Trait for models that belong to a company (tenant).
 *
 * Automatically:
 * - Scopes all queries to the current company
 * - Sets company_id on model creation
 * - Defines the company() relationship
 */
trait CompanyOwned
{
    public static function bootCompanyOwned(): void
    {
        // Automatically scope all queries to the current company
        static::addGlobalScope('company', function (Builder $builder) {
            $companyId = app(CompanyAccessor::class)->getCurrentCompanyId();

            if ($companyId !== null) {
                $foreignKey = config('accounting.company_foreign_key', 'company_id');
                $builder->where(
                    $builder->getModel()->qualifyColumn($foreignKey),
                    $companyId
                );
            }
        });

        // Automatically set company_id when creating a new record
        static::creating(function (Model $model) {
            $foreignKey = config('accounting.company_foreign_key', 'company_id');

            if (empty($model->{$foreignKey})) {
                $companyId = app(CompanyAccessor::class)->getCurrentCompanyId();

                if ($companyId !== null) {
                    $model->{$foreignKey} = $companyId;
                }
            }
        });
    }

    /**
     * Get the company that owns this record.
     */
    public function company(): BelongsTo
    {
        $companyModel = config('accounting.company_model', 'App\\Models\\Company');
        $foreignKey = config('accounting.company_foreign_key', 'company_id');

        return $this->belongsTo($companyModel, $foreignKey);
    }

    /**
     * Scope query to a specific company, bypassing the global scope.
     */
    public function scopeForCompany(Builder $query, int $companyId): Builder
    {
        $foreignKey = config('accounting.company_foreign_key', 'company_id');

        return $query->withoutGlobalScope('company')->where($foreignKey, $companyId);
    }
}
