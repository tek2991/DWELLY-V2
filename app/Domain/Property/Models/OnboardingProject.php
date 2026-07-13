<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingProject extends DomainModel
{
    protected $table = 'onboarding_projects';

    protected $fillable = [
        'property_id',
        'status',
        'assigned_executive_id',
    ];

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function assignedExecutive(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_executive_id');
    }
}
