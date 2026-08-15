<?php

namespace App\Domain\Maintenance\Models;

use App\Domain\Audit\Models\Audit;
use App\Domain\Finance\Models\VendorBill;
use App\Domain\Maintenance\Enums\MaintenancePriority;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Enums\PayerType;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Shared\Models\DomainModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class MaintenanceRequest extends DomainModel implements HasMedia
{
    use SoftDeletes, InteractsWithMedia;

    protected $table = 'maintenance_requests';

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('issue_photos');
        $this->addMediaCollection('repair_proofs');
        $this->addMediaCollection('quotation_files');
        $this->addMediaCollection('quotation_approval_proofs');
        $this->addMediaCollection('direct_payment_receipts');
    }

    protected $fillable = [
        'ticket_number',
        'property_id',
        'tenant_id',
        'owner_id',
        'vendor_party_id',
        'assigned_inspector_id',
        'reporter_type',
        'title',
        'description',
        'priority',
        'status',
        'payer_type',
        'is_direct_vendor',
        'is_dwelly_involved',
        'current_client_quote_id',
        'quotation_amount',
        'quotation_status',
        'quotation_notes',
        'quotation_approved_at',
        'quotation_approval_notes',
        'total_cost',
        'vendor_cost',
        'total_vendor_cost',
        'total_client_cost',
        'dwelly_amount',
        'owner_amount',
        'tenant_amount',
        'direct_payment_reference',
        'direct_payment_notes',
        'bill_id',
        'owner_invoice_id',
        'tenant_invoice_id',
        'triggered_audit_id',
        'assigned_at',
        'completed_at',
        'resolved_at',
        'created_by_id',
    ];

    protected $casts = [
        'priority' => MaintenancePriority::class,
        'status' => MaintenanceStatus::class,
        'payer_type' => PayerType::class,
        'is_direct_vendor' => 'boolean',
        'is_dwelly_involved' => 'boolean',
        'quotation_amount' => 'decimal:2',
        'total_cost' => 'decimal:2',
        'vendor_cost' => 'decimal:2',
        'total_vendor_cost' => 'decimal:2',
        'total_client_cost' => 'decimal:2',
        'dwelly_amount' => 'decimal:2',
        'owner_amount' => 'decimal:2',
        'tenant_amount' => 'decimal:2',
        'quotation_approved_at' => 'datetime',
        'assigned_at' => 'datetime',
        'completed_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($request) {
            if (empty($request->ticket_number)) {
                $request->ticket_number = self::generateTicketNumber();
            }
            if (empty($request->owner_id) && $request->property_id) {
                $mouPartyId = \App\Domain\Mou\Models\Mou::where('property_id', $request->property_id)->whereNotNull('party_id')->latest()->value('party_id');
                if ($mouPartyId) {
                    $request->owner_id = $mouPartyId;
                }
            }
        });

        static::saving(function ($request) {
            if (empty($request->owner_id) && $request->property_id) {
                $mouPartyId = \App\Domain\Mou\Models\Mou::where('property_id', $request->property_id)->whereNotNull('party_id')->latest()->value('party_id');
                if ($mouPartyId) {
                    $request->owner_id = $mouPartyId;
                }
            }
        });

        static::updated(function ($request) {
            if ($request->isDirty('status') && ($request->status === MaintenanceStatus::CLOSED || $request->status === MaintenanceStatus::CLOSED->value)) {
                if ($request->triggered_audit_id && $request->triggeredAudit) {
                    app(\App\Domain\Audit\Services\AuditReviewService::class)->lockAudit($request->triggeredAudit, auth()->user());
                }
            }
        });
    }

    public static function generateTicketNumber(): string
    {
        $year = date('Y');
        $latest = static::withTrashed()
            ->where('ticket_number', 'like', "MNT-{$year}-%")
            ->orderBy('ticket_number', 'desc')
            ->first();

        if ($latest) {
            $lastNumber = (int) substr($latest->ticket_number, -5);
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '00001';
        }

        return "MNT-{$year}-{$newNumber}";
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'tenant_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'owner_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'vendor_party_id');
    }

    public function assignedInspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_inspector_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MaintenanceRequestItem::class, 'maintenance_request_id');
    }

    public function vendorQuotes(): HasMany
    {
        return $this->hasMany(MaintenanceVendorQuote::class, 'maintenance_request_id');
    }

    public function clientQuotes(): HasMany
    {
        return $this->hasMany(MaintenanceClientQuote::class, 'maintenance_request_id');
    }

    public function currentClientQuote(): BelongsTo
    {
        return $this->belongsTo(MaintenanceClientQuote::class, 'current_client_quote_id');
    }

    public function isDirectRepair(): bool
    {
        return (bool) $this->is_direct_vendor;
    }

    public function isDwellyCoordinated(): bool
    {
        return !$this->is_direct_vendor;
    }

    public function syncQuotationTotals(): void
    {
        $totalVendor = (float) $this->vendorQuotes()->sum('quoted_cost');
        $this->total_vendor_cost = $totalVendor;
        $this->vendor_cost = $totalVendor;

        $clientQuote = $this->currentClientQuote ?? $this->clientQuotes()->latest()->first();
        if ($clientQuote) {
            $this->total_client_cost = (float) $clientQuote->total_amount;
            $this->quotation_amount = (float) $clientQuote->total_amount;
            $this->total_cost = (float) $clientQuote->total_amount;
            $this->owner_amount = (float) $clientQuote->owner_amount;
            $this->tenant_amount = (float) $clientQuote->tenant_amount;
            $this->dwelly_amount = (float) $clientQuote->dwelly_amount;
        } elseif ($this->isDirectRepair()) {
            $this->total_client_cost = 0.00;
        }

        $this->saveQuietly();
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(VendorBill::class, 'bill_id');
    }

    public function ownerInvoice(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Finance\Models\JournalEntry::class, 'owner_invoice_id'); // Or invoice model
    }

    public function tenantInvoice(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Finance\Models\RentPayment::class, 'tenant_invoice_id'); // Or invoice model
    }

    public function triggeredAudit(): BelongsTo
    {
        return $this->belongsTo(Audit::class, 'triggered_audit_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
