<?php

namespace Tek2991\Accounting\Concerns;

use Illuminate\Database\Eloquent\Model;

/**
 * Trait that automatically records who created and last updated a record.
 *
 * Requires `created_by` and `updated_by` columns on the table.
 */
trait Blamable
{
    public static function bootBlamable(): void
    {
        static::creating(function (Model $model) {
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
                $model->updated_by = auth()->id();
            }
        });

        static::updating(function (Model $model) {
            if (auth()->check()) {
                $model->updated_by = auth()->id();
            }
        });
    }

    /**
     * Get the user who created this record.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        $userModel = config('accounting.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'created_by');
    }

    /**
     * Get the user who last updated this record.
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        $userModel = config('accounting.user_model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'updated_by');
    }
}
