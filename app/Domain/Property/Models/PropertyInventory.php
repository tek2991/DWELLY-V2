<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PropertyInventory extends DomainModel
{
    protected $table = 'property_inventories';

    protected $fillable = [
        'property_id',
        'property_room_id',
        'inventory_type_id',
        'count',
    ];

    public function inventoryType(): BelongsTo
    {
        return $this->belongsTo(InventoryType::class);
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }
    
    public function room(): BelongsTo
    {
        return $this->belongsTo(PropertyRoom::class, 'property_room_id');
    }
}
