<?php

namespace App\Domain\Audit\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Property\Models\Property;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Audit extends DomainModel
{
    use SoftDeletes, LogsActivity;

    protected $table = 'audits';

    protected $casts = [
        'audit_type' => AuditType::class,
        'status' => AuditStatus::class,
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($audit) {
            if (empty($audit->audit_number)) {
                $audit->audit_number = self::generateAuditNumber();
            }
        });

        static::updating(function ($audit) {
            if ($audit->getOriginal('status') === AuditStatus::APPROVED) {
                // Strictly lock the model once approved
                return false; 
            }
        });

        static::created(function ($audit) {
            // Trigger snapshot generation immediately upon creation
            app(\App\Domain\Audit\Services\AuditSnapshotService::class)->generateSnapshot($audit);
        });
    }

    public static function generateAuditNumber(): string
    {
        $year = date('Y');
        // Get latest audit number for current year, even if deleted
        $latest = static::withTrashed()
            ->where('audit_number', 'like', "AUD-{$year}-%")
            ->orderBy('audit_number', 'desc')
            ->first();

        if ($latest) {
            $lastNumber = (int) substr($latest->audit_number, -5);
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '00001';
        }

        return "AUD-{$year}-{$newNumber}";
    }

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function referenceAudit(): BelongsTo
    {
        return $this->belongsTo(Audit::class, 'reference_audit_id');
    }

    public function inspector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspector_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function categories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditCategory::class)->orderBy('sort_order');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(AuditItem::class, AuditCategory::class);
    }
}
