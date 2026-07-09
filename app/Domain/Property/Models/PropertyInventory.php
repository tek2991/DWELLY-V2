<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;

class PropertyInventory extends DomainModel
{
    protected $table = 'property_inventories';

    protected $fillable = [
        'property_id',
        'inventory_type_id',
        'count',
    ];

    public function inventoryType(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(InventoryType::class);
    }
}
