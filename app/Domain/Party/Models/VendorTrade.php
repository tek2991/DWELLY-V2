<?php

namespace App\Domain\Party\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorTrade extends DomainModel
{
    protected $table = 'vendor_trades';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function vendorProfiles(): HasMany
    {
        return $this->hasMany(VendorProfile::class, 'vendor_trade_id');
    }
}
