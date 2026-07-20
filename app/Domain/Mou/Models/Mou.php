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
        'version',
        'opportunity_id',
        'party_id',
        'is_signatory_different',
        'signatory_name',
        'signatory_phone',
        'signatory_email',
        'signatory_aadhar_number',
        'signatory_pan_number',
        'signatory_relation',
        'status',
        'legal_terms',
        'bank_details',
        'verified_at',
        'verified_by',
        'prepared_by',
        'generated_by',
        'cancelled_at',
        'expires_at',
        'start_date',
    ];

    protected $casts = [
        'is_signatory_different' => 'boolean',
        'status' => MouStatus::class,
        'legal_terms' => 'array',
        'bank_details' => 'array',
        'verified_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'start_date' => 'date',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('draft_pdf');
        $this->addMediaCollection('signed_pdf')->singleFile();
        $this->addMediaCollection('annexures');
        $this->addMediaCollection('owner_documents');
        $this->addMediaCollection('property_documents');
        $this->addMediaCollection('mou_attachments');
        $this->addMediaCollection('signatory_documents');
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

    public function preparedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function property(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(\App\Domain\Property\Models\Property::class, 'mou_id');
    }
}
