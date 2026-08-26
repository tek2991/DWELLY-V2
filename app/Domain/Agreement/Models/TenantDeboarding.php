<?php

namespace App\Domain\Agreement\Models;

use App\Domain\Agreement\Enums\DeboardingStatus;
use App\Domain\Audit\Models\Audit;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Shared\Models\DomainModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TenantDeboarding extends DomainModel implements HasMedia
{
    use SoftDeletes, LogsActivity, InteractsWithMedia;

    protected $table = 'tenant_deboardings';

    protected $fillable = [
        'code',
        'tenancy_agreement_id',
        'property_id',
        'tenant_id',
        'status',
        'notice_date',
        'target_vacating_date',
        'actual_vacating_date',
        'reason',
        'notes',
        'move_out_audit_id',
        'damages_identified',
        'damage_notes',
        'total_repair_cost',
        'tenant_repair_share',
        'owner_repair_share',
        'dwelly_repair_share',
        'keys_returned',
        'keys_returned_at',
        'keys_received_by_id',
        'key_handover_remarks',
        'key_details',
        'security_deposit_held',
        'unpaid_rent_deduction',
        'maintenance_deduction',
        'utility_deduction',
        'other_deductions',
        'other_deductions_notes',
        'total_deductions',
        'net_deposit_refund',
        'excess_due_from_tenant',
        'settlement_status',
        'refund_payment_mode',
        'refund_transaction_reference',
        'refund_bank_details',
        'refunded_at',
        'new_property_status',
        'completed_at',
        'completed_by_id',
    ];

    protected $casts = [
        'status' => DeboardingStatus::class,
        'notice_date' => 'date',
        'target_vacating_date' => 'date',
        'actual_vacating_date' => 'date',
        'keys_returned_at' => 'datetime',
        'refunded_at' => 'datetime',
        'completed_at' => 'datetime',
        'keys_returned' => 'boolean',
        'damages_identified' => 'boolean',
        'total_repair_cost' => 'decimal:2',
        'tenant_repair_share' => 'decimal:2',
        'owner_repair_share' => 'decimal:2',
        'dwelly_repair_share' => 'decimal:2',
        'security_deposit_held' => 'decimal:2',
        'unpaid_rent_deduction' => 'decimal:2',
        'maintenance_deduction' => 'decimal:2',
        'utility_deduction' => 'decimal:2',
        'other_deductions' => 'decimal:2',
        'total_deductions' => 'decimal:2',
        'net_deposit_refund' => 'decimal:2',
        'excess_due_from_tenant' => 'decimal:2',
        'key_details' => 'array',
        'refund_bank_details' => 'array',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'keys_returned', 'settlement_status', 'net_deposit_refund'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($deboarding) {
            if (empty($deboarding->code)) {
                $deboarding->code = self::generateDeboardingCode();
            }
        });
    }

    public static function generateDeboardingCode(): string
    {
        $year = date('Y');
        $latest = static::withTrashed()
            ->where('code', 'like', "DEB-{$year}-%")
            ->orderBy('code', 'desc')
            ->first();

        if ($latest) {
            $lastNumber = (int) substr($latest->code, -5);
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '00001';
        }

        return "DEB-{$year}-{$newNumber}";
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('key_return_photos');
        $this->addMediaCollection('damage_photos');
        $this->addMediaCollection('refund_payment_proofs');
        $this->addMediaCollection('settlement_documents');
    }

    public function tenancyAgreement(): BelongsTo
    {
        return $this->belongsTo(TenancyAgreement::class, 'tenancy_agreement_id');
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'tenant_id');
    }

    public function moveOutAudit(): BelongsTo
    {
        return $this->belongsTo(Audit::class, 'move_out_audit_id');
    }

    public function maintenanceRequests(): HasMany
    {
        return $this->hasMany(MaintenanceRequest::class, 'tenant_deboarding_id');
    }

    public function keysReceivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'keys_received_by_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    /**
     * Recalculate financial breakdown totals for deposit refund.
     */
    public function recalculateSettlement(): void
    {
        $depositHeld = (float) ($this->security_deposit_held ?? 0.00);
        if ($depositHeld <= 0 && $this->tenancyAgreement) {
            $depositHeld = (float) ($this->tenancyAgreement->security_deposit ?? 0.00);
            $this->security_deposit_held = $depositHeld;
        }

        // Maintenance tenant share sum
        $maintTenantTotal = (float) $this->maintenanceRequests()->sum('tenant_amount');
        if ($maintTenantTotal > 0 && (float)$this->maintenance_deduction <= 0) {
            $this->maintenance_deduction = $maintTenantTotal;
        }

        $totalDeductions = (float) $this->unpaid_rent_deduction
            + (float) $this->maintenance_deduction
            + (float) $this->utility_deduction
            + (float) $this->other_deductions;

        $this->total_deductions = round($totalDeductions, 2);

        $netRefund = $depositHeld - $totalDeductions;
        if ($netRefund >= 0) {
            $this->net_deposit_refund = round($netRefund, 2);
            $this->excess_due_from_tenant = 0.00;
        } else {
            $this->net_deposit_refund = 0.00;
            $this->excess_due_from_tenant = round(abs($netRefund), 2);
        }
    }
}
