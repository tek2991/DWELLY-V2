<?php

namespace App\Domain\Opportunity\Models;

use App\Domain\Opportunity\Enums\OpportunityStatus;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\PropertyType;
use App\Domain\Shared\Models\DomainModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Opportunity extends DomainModel implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'opportunities';

    protected $fillable = [
        'number',
        'title',
        'status',
        'opportunity_source_id',
        'lead_origin_id',
        'assigned_user_id',
        'owner_party_id',
        'owner_name',
        'owner_phone',
        'owner_email',
        'address',
        'estimated_property_type_id',
        'estimated_bhk',
        'estimated_size',
        'estimated_is_furnished',
        'expected_rent',
        'expected_financial_model_id',
        'internal_summary',
        'expected_onboarding_date',
    ];

    protected $casts = [
        'status' => OpportunityStatus::class,
        'estimated_is_furnished' => 'boolean',
        'expected_rent' => 'decimal:2',
        'expected_onboarding_date' => 'date',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos');
        $this->addMediaCollection('floor_plans');
        $this->addMediaCollection('draft_mou');
        $this->addMediaCollection('signed_mou');
        $this->addMediaCollection('other_documents');
    }

    public function opportunitySource(): BelongsTo
    {
        return $this->belongsTo(OpportunitySource::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function estimatedPropertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'estimated_property_type_id');
    }

    public function expectedFinancialModel(): BelongsTo
    {
        return $this->belongsTo(FinancialModel::class, 'expected_financial_model_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(OpportunityActivity::class)->orderBy('performed_at', 'desc');
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(OpportunitySnapshot::class)->orderBy('created_at', 'desc');
    }

    public function ownerParty(): BelongsTo
    {
        return $this->belongsTo(Party::class, 'owner_party_id');
    }

    public function mou(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Domain\Mou\Models\Mou::class);
    }
}
