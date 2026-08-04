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
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Audit extends DomainModel implements HasMedia
{
    use SoftDeletes, LogsActivity, InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('layout_video')->singleFile();
    }

    protected $table = 'audits';

    protected $fillable = [
        'audit_number',
        'property_id',
        'tenant_id',
        'audit_type',
        'status',
        'reference_audit_id',
        'inspector_id',
        'scheduled_at',
        'completed_at',
        'completed_by_id',
        'approved_at',
        'approved_by_id',
        'notes',
        'reviewer_id',
        'review_round',
        'submitted_at',
        'review_started_at',
        'is_locked',
        'locked_at',
        'locked_by_id',
    ];

    protected $casts = [
        'audit_type' => AuditType::class,
        'status' => AuditStatus::class,
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'approved_at' => 'datetime',
        'submitted_at' => 'datetime',
        'review_started_at' => 'datetime',
        'is_locked' => 'boolean',
        'locked_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'is_locked'])
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
            if (empty($audit->reviewer_id) && auth()->check()) {
                $audit->reviewer_id = auth()->id();
            }
        });

        static::updating(function ($audit) {
            if ($audit->getOriginal('is_locked')) {
                // Strictly lock the model once permanently locked
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

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Domain\Party\Models\Party::class, 'tenant_id');
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

    public function lockedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_id');
    }

    public function categories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AuditCategory::class)->orderBy('sort_order');
    }

    public function items(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(AuditItem::class, AuditCategory::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    // Policy Methods

    public function isInspector(?User $user = null): bool
    {
        $userId = $user ? $user->id : auth()->id();
        return !empty($this->inspector_id) && (string)$this->inspector_id === (string)$userId;
    }

    public function canStart(?User $user = null): bool
    {
        return !$this->is_locked && $this->status === AuditStatus::DRAFT && $this->isInspector($user);
    }

    public function canInspect(?User $user = null): bool
    {
        return !$this->is_locked && in_array($this->status, [AuditStatus::IN_PROGRESS, AuditStatus::PARTIALLY_APPROVED]) && $this->isInspector($user);
    }

    public function canSubmit(): bool
    {
        return !$this->is_locked && in_array($this->status, [AuditStatus::DRAFT, AuditStatus::IN_PROGRESS, AuditStatus::PARTIALLY_APPROVED]) && $this->isInspector();
    }

    public function canReview(): bool
    {
        return !$this->is_locked && in_array($this->status, [AuditStatus::PENDING_REVIEW, AuditStatus::IN_REVIEW]);
    }

    public function canRequestChanges(): bool
    {
        return $this->canReview();
    }

    public function canApprove(): bool
    {
        return !$this->is_locked && $this->status === AuditStatus::IN_REVIEW;
    }

    public function canReopen(): bool
    {
        $statusVal = $this->status instanceof AuditStatus ? $this->status->value : (string)$this->status;
        return !$this->is_locked && in_array($statusVal, ['approved', 'completed']);
    }

    public function canLock(): bool
    {
        $statusVal = $this->status instanceof AuditStatus ? $this->status->value : (string)$this->status;
        return !$this->is_locked && in_array($statusVal, ['approved', 'completed']);
    }

    public function isImmutable(): bool
    {
        return $this->is_locked || in_array($this->status, [AuditStatus::APPROVED, AuditStatus::COMPLETED]);
    }
}
