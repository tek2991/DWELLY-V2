<?php

namespace App\Domain\Implementation\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImplementationProjectPhase extends DomainModel
{
    protected $table = 'implementation_project_phases';

    public function project(): BelongsTo
    {
        return $this->belongsTo(ImplementationProject::class, 'project_id');
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ImplementationProjectStage::class, 'phase_id')->orderBy('order');
    }

    public function getStatusAttribute(): string
    {
        if ($this->stages->isEmpty()) {
            return 'not_started';
        }

        $allCompletedOrSkipped = $this->stages->every(fn($stage) => in_array($stage->status, ['completed', 'skipped']));
        if ($allCompletedOrSkipped) {
            return 'completed';
        }

        $anyActiveOrCompleted = $this->stages->contains(fn($stage) => in_array($stage->status, ['active', 'completed']));
        if ($anyActiveOrCompleted) {
            return 'active';
        }

        return 'not_started';
    }
}
