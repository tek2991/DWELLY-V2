<?php

namespace App\Domain\Mou\Models;

use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Party\Models\Party;
use App\Domain\Shared\Models\DomainModel;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Mou extends DomainModel implements HasMedia
{
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'mous';

    protected $fillable = [
        'number',
        'opportunity_id',
        'party_id',
        'status',
        'legal_terms',
        'bank_details',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'status' => MouStatus::class,
        'legal_terms' => 'array',
        'bank_details' => 'array',
        'verified_at' => 'datetime',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('draft_mou')->singleFile();
        $this->addMediaCollection('signed_mou')->singleFile();
        $this->addMediaCollection('supporting_documents');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
