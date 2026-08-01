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
        'is_dwelly_involved',
        'total_cost',
        'vendor_cost',
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
        'is_dwelly_involved' => 'boolean',
        'total_cost' => 'decimal:2',
        'vendor_cost' => 'decimal:2',
        'dwelly_amount' => 'decimal:2',
        'owner_amount' => 'decimal:2',
        'tenant_amount' => 'decimal:2',
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
