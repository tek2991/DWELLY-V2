<?php

namespace App\Domain\Opportunity\Models;

use App\Domain\Shared\Models\DomainModel;

class OpportunitySource extends DomainModel
{
    protected $table = 'opportunity_sources';

    protected $fillable = ['parent_id', 'name', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
