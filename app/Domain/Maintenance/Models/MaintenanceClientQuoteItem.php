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
        'description',
        'quantity',
        'unit_price',
        'total_price',
        'sort_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'sort_order' => 'integer',
    ];

    public function clientQuote(): BelongsTo
    {
        return $this->belongsTo(MaintenanceClientQuote::class, 'maintenance_client_quote_id');
    }

    public function vendorQuote(): BelongsTo
    {
        return $this->belongsTo(MaintenanceVendorQuote::class, 'vendor_quote_id');
    }
}
