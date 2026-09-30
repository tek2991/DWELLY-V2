<?php

namespace App\Domain\Mou\Models;

use App\Domain\Mou\Enums\MouType;
use App\Domain\Opportunity\Enums\MouStatus;
use App\Domain\Opportunity\Models\Opportunity;
use App\Domain\Party\Models\Party;
use App\Domain\Property\Models\Property;
use App\Domain\Shared\Models\DomainModel;
use App\Domain\Shared\Traits\BelongsToBranch;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Mou extends DomainModel implements HasMedia
{
    use BelongsToBranch;
    use InteractsWithMedia;
    use SoftDeletes;

    protected $table = 'mous';

    protected $fillable = [
        'branch_id',
        'number',
        'version',
        'opportunity_id',
        'property_id',
        'type',
        'party_id',
        'owner_details',
        'signatory_details',
        'is_signatory_different',
        'status',
        'kyc_documents',
        'legal_terms',
        'bank_details',
        'verified_at',
        'verified_by',
        'prepared_by',
        'generated_by',
        'cancelled_at',
        'expires_at',
        'start_date',
        'agreement_content',
        'agreement_customized_at',
    ];

    protected $casts = [
        'kyc_documents' => 'array',
        'legal_terms' => 'array',
        'bank_details' => 'array',
        'owner_details' => 'array',
        'signatory_details' => 'array',
        'is_signatory_different' => 'boolean',
        'status' => MouStatus::class,
        'type' => MouType::class,
        'verified_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expires_at' => 'datetime',
        'start_date' => 'date',
        'agreement_customized_at' => 'datetime',
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
        $this->addMediaCollection('owner_aadhaar');
        $this->addMediaCollection('owner_pan');
        $this->addMediaCollection('cancelled_cheque');
        $this->addMediaCollection('electricity_bill');
        $this->addMediaCollection('signatory_aadhaar');
        $this->addMediaCollection('signatory_pan');
        $this->addMediaCollection('signatory_poa');
    }

    public function getDocumentCollectionName(string $code, string $context = 'owner'): string
    {
        if ($context === 'signatory') {
            return match ($code) {
                'power_of_attorney' => 'signatory_poa',
                default => "signatory_{$code}",
            };
        }

        return match ($code) {
            'cancelled_cheque' => 'cancelled_cheque',
            'electricity_bill' => 'electricity_bill',
            default => "owner_{$code}",
        };
    }

    public function hasDocument(string $code, string $context = 'owner'): bool
    {
        $collection = $this->getDocumentCollectionName($code, $context);
        if ($this->hasMedia($collection)) {
            return true;
        }

        return $this->getMedia($context === 'signatory' ? 'signatory_documents' : 'mou_attachments')
            ->contains(fn ($media) => $media->getCustomProperty('document_type') === $code);
    }

    public function getDocumentMedia(string $code, string $context = 'owner')
    {
        $collection = $this->getDocumentCollectionName($code, $context);
        $media = $this->getMedia($collection);
        if ($media->isNotEmpty()) {
            return $media;
        }

        return $this->getMedia($context === 'signatory' ? 'signatory_documents' : 'mou_attachments')
            ->filter(fn ($media) => $media->getCustomProperty('document_type') === $code);
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

    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    public function getResolvedPanNumber(): ?string
    {
        $candidate = $this->kyc_documents['owner']['pan']['number']
            ?? $this->owner_details['pan_number']
            ?? $this->owner_details['pan']
            ?? null;

        if (empty($candidate) && ! $this->is_signatory_different) {
            $candidate = $this->kyc_documents['signatory']['pan']['number']
                ?? $this->signatory_details['pan_number']
                ?? null;
        }

        if (empty($candidate) || ! is_string($candidate)) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($candidate)));

        return preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $normalized) ? $normalized : null;
    }

    public function getResolvedAadhaarNumber(): ?string
    {
        $candidate = $this->kyc_documents['owner']['aadhaar']['number']
            ?? $this->owner_details['aadhar_number']
            ?? $this->owner_details['aadhaar_number']
            ?? null;

        if (empty($candidate) && ! $this->is_signatory_different) {
            $candidate = $this->kyc_documents['signatory']['aadhaar']['number']
                ?? $this->signatory_details['aadhar_number']
                ?? null;
        }

        if (empty($candidate) || ! is_string($candidate)) {
            return null;
        }

        return trim($candidate);
    }

    public function getResolvedVoterId(): ?string
    {
        $candidate = $this->kyc_documents['owner']['voter_id']['number']
            ?? $this->owner_details['voter_id']
            ?? null;

        if (empty($candidate) || ! is_string($candidate)) {
            return null;
        }

        return strtoupper(trim($candidate));
    }
}
