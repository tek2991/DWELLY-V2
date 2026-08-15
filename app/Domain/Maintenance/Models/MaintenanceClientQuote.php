<?php

namespace App\Domain\Maintenance\Models;

use App\Domain\Shared\Models\DomainModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MaintenanceClientQuote extends DomainModel implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'maintenance_client_quotes';

    protected $fillable = [
        'maintenance_request_id',
        'quote_number',
        'version',
        'total_amount',
        'owner_amount',
        'tenant_amount',
        'dwelly_amount',
        'status',
        'generated_at',
        'awarded_vendor_quote_ids',
        'rejection_reason',
        'rejection_action',
        'approved_at',
        'approval_notes',
    ];

    protected $casts = [
        'version' => 'integer',
        'awarded_vendor_quote_ids' => 'array',
        'total_amount' => 'decimal:2',
        'owner_amount' => 'decimal:2',
        'tenant_amount' => 'decimal:2',
        'dwelly_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'generated_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('generated_quote_pdf');
        $this->addMediaCollection('client_quote_files');
        $this->addMediaCollection('approval_proof_files');
        $this->addMediaCollection('vendor_work_photos');
        $this->addMediaCollection('vendor_invoice_files');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($quote) {
            if (empty($quote->quote_number)) {
                $quote->quote_number = self::generateQuoteNumber();
            }
        });
    }

    public static function generateQuoteNumber(): string
    {
        $year = date('Y');
        $latest = static::where('quote_number', 'like', "QTE-{$year}-%")
            ->orderBy('quote_number', 'desc')
            ->first();

        if ($latest) {
            $lastNumber = (int) substr($latest->quote_number, -5);
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '00001';
        }

        return "QTE-{$year}-{$newNumber}";
    }

    public function maintenanceRequest(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRequest::class, 'maintenance_request_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceClientQuoteItem::class, 'maintenance_client_quote_id')->orderBy('sort_order');
    }

    public function vendorQuotes(): HasMany
    {
        return $this->hasMany(MaintenanceVendorQuote::class, 'maintenance_request_id', 'maintenance_request_id');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval']);
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function recalculateTotals(): self
    {
        $total = $this->items()->sum('total_price');
        $this->total_amount = $total;
        $this->save();
        return $this;
    }
}
