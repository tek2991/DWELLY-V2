<?php

namespace App\Domain\Maintenance\Models;

use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\VendorTrade;
use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Tek2991\Accounting\Models\Bill;

class MaintenanceVendorQuote extends DomainModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'maintenance_vendor_quotes';

    protected $fillable = [
        'maintenance_request_id',
        'maintenance_request_item_id',
        'maintenance_request_item_ids',
        'vendor_party_id',
        'vendor_trade_id',
        'trade_title',
        'vendor_quote_number',
        'vendor_quote_date',
        'scope_of_work',
        'quoted_cost',
        'final_cost',
        'status',
        'is_awarded',
        'work_order_number',
        'work_order_issued_at',
        'bill_id',
        'assigned_at',
        'completed_at',
        'notes',
    ];

    protected $casts = [
        'maintenance_request_item_ids' => 'array',
        'is_awarded' => 'boolean',
        'vendor_quote_date' => 'date',
        'work_order_issued_at' => 'datetime',
        'quoted_cost' => 'decimal:2',
        'final_cost' => 'decimal:2',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('vendor_quote_files');
        $this->addMediaCollection('work_order_letter_pdf');
        $this->addMediaCollection('vendor_work_photos');
        $this->addMediaCollection('vendor_invoice_files');
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function defectItem(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequestItem::class, 'maintenance_request_item_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'vendor_party_id');
    }

    public function vendorTrade(): BelongsTo
    {
        return $this->belongsTo(VendorTrade::class, 'vendor_trade_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class, 'bill_id');
    }
}
