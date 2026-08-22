<?php

namespace App\Domain\Maintenance\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceClientQuoteItem extends DomainModel
{
    protected $table = 'maintenance_client_quote_items';

    protected $fillable = [
        'maintenance_client_quote_id',
        'vendor_quote_id',
        'maintenance_request_item_id',
        'maintenance_request_item_ids',
        'description',
        'quantity',
        'vendor_cost',
        'unit_price',
        'total_price',
        'unit_rate',
        'total_cost',
        'sort_order',
    ];

    protected $appends = [
        'unit_rate',
        'total_cost',
    ];

    protected $casts = [
        'maintenance_request_item_ids' => 'array',
        'quantity' => 'decimal:2',
        'vendor_cost' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            $qty = (float) ($item->quantity ?? 1);
            $price = (float) ($item->unit_price ?? $item->unit_rate ?? 0);
            $item->vendor_cost = (float) ($item->vendor_cost ?? 0.00);

            if (blank($item->total_price) || $item->isDirty(['quantity', 'unit_price', 'unit_rate'])) {
                $item->total_price = round($qty * $price, 2);
            }

            // Sync single ID and multi-IDs array
            if (! empty($item->maintenance_request_item_ids) && is_array($item->maintenance_request_item_ids)) {
                if (empty($item->maintenance_request_item_id)) {
                    $item->maintenance_request_item_id = $item->maintenance_request_item_ids[0] ?? null;
                }
            } elseif (! empty($item->maintenance_request_item_id)) {
                $item->maintenance_request_item_ids = [$item->maintenance_request_item_id];
            }
        });
    }

    public function clientQuote(): BelongsTo
    {
        return $this->belongsTo(MaintenanceClientQuote::class, 'maintenance_client_quote_id');
    }

    public function vendorQuote(): BelongsTo
    {
        return $this->belongsTo(MaintenanceVendorQuote::class, 'vendor_quote_id');
    }

    public function maintenanceRequestItem(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequestItem::class, 'maintenance_request_item_id');
    }

    public function defectItem(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequestItem::class, 'maintenance_request_item_id');
    }

    /**
     * Get Collection of associated defect items
     */
    public function getDefectItemsAttribute()
    {
        $ids = $this->maintenance_request_item_ids;
        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?: [$ids];
        }
        $ids = (array) ($ids ?: ($this->maintenance_request_item_id ? [$this->maintenance_request_item_id] : []));
        $ids = array_values(array_filter($ids));

        if (empty($ids)) {
            return collect();
        }

        return MaintenanceRequestItem::with('itemable')->whereIn('id', $ids)->get();
    }

    public function getUnitRateAttribute(): ?float
    {
        return (float) ($this->unit_price ?? 0);
    }

    public function setUnitRateAttribute($value): void
    {
        $this->attributes['unit_price'] = $value;
    }

    public function getTotalCostAttribute(): ?float
    {
        return (float) ($this->total_price ?? 0);
    }

    public function setTotalCostAttribute($value): void
    {
        $this->attributes['total_price'] = $value;
    }
}
