<?php

namespace App\Domain\Task\Models;

use App\Domain\Property\Models\Property;
use App\Domain\Shared\Models\DomainModel;
use App\Domain\Task\Enums\TaskCategory;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Enums\TaskStatus;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Task extends DomainModel implements HasMedia
{
    use SoftDeletes, LogsActivity, InteractsWithMedia;

    protected $table = 'tasks';

    protected $fillable = [
        'task_number',
        'branch_id',
        'property_id',
        'taskable_type',
        'taskable_id',
        'template_id',
        'category',
        'title',
        'description',
        'priority',
        'status',
        'scheduled_at',
        'due_date',
        'sla_hours',
        'assigned_to_id',
        'created_by_id',
        'completed_at',
        'completed_by_id',
        'verified_by_id',
        'resolution_notes',
        'cancellation_reason',
    ];

    protected $casts = [
        'category' => TaskCategory::class,
        'priority' => TaskPriority::class,
        'status' => TaskStatus::class,
        'scheduled_at' => 'datetime',
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'sla_hours' => 'integer',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('task_attachments');
        $this->addMediaCollection('proof_photos');
        $this->addMediaCollection('completion_proofs');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'priority', 'assigned_to_id', 'due_date', 'resolution_notes'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /* Relationships */

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class, 'property_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(TaskTemplate::class, 'template_id');
    }

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_id');
    }

    public function checklistItems(): HasMany
    {
        return $this->hasMany(TaskChecklistItem::class, 'task_id')->orderBy('sort_order');
    }

    /* Scopes & Helpers */

    public function getIsOverdueAttribute(): bool
    {
        if (! $this->due_date) {
            return false;
        }

        if (in_array($this->status, [TaskStatus::COMPLETED, TaskStatus::CANCELLED])) {
            return false;
        }

        return $this->due_date->isPast();
    }

    public function getChecklistProgressAttribute(): string
    {
        $total = $this->checklistItems()->count();
        if ($total === 0) {
            return 'N/A';
        }

        $completed = $this->checklistItems()->where('is_completed', true)->count();
        return "{$completed}/{$total}";
    }

    public function getAllMandatoryChecklistCompletedAttribute(): bool
    {
        return ! $this->checklistItems()
            ->where('is_mandatory', true)
            ->where('is_completed', false)
            ->exists();
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereNotIn('status', [TaskStatus::COMPLETED->value, TaskStatus::CANCELLED->value])
            ->whereNotNull('due_date')
            ->where('due_date', '<', now());
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('assigned_to_id', $userId);
    }
}
