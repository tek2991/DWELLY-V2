<?php

namespace App\Domain\Shared\Traits;

use App\Domain\Shared\Scopes\BranchScope;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tek2991\Accounting\Services\BranchContext;

trait BelongsToBranch
{
    public static function bootBelongsToBranch(): void
    {
        static::addGlobalScope(new BranchScope);

        static::creating(function ($model) {
            if (empty($model->branch_id)) {
                $currentBranchId = app(BranchContext::class)->getCurrentId();

                if ($currentBranchId) {
                    $model->branch_id = $currentBranchId;
                } elseif (auth()->check()) {
                    $user = auth()->user();
                    $firstBranch = method_exists($user, 'branches') ? $user->branches()->first() : null;
                    if ($firstBranch) {
                        $model->branch_id = $firstBranch->id;
                    }
                }
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }
}
