<?php

namespace App\Domain\Property\Models;

use App\Domain\Geographic\Models\Locality;
use App\Domain\Maintenance\Enums\MaintenanceStatus;
use App\Domain\Maintenance\Models\MaintenanceRequest;
use App\Domain\Shared\Models\DomainModel;
use App\Domain\Shared\Traits\BelongsToBranch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Property extends DomainModel
{
    use LogsActivity, BelongsToBranch;

    protected $table = 'properties';

    protected $casts = [
        'is_listed' => 'boolean',
        'is_promoted' => 'boolean',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty();
    }

    public function localityRef(): BelongsTo
    {
        return $this->belongsTo(Locality::class, 'locality_id');
    }

    public function locality(): BelongsTo
    {
        return $this->localityRef();
    }

    public function bhkType(): BelongsTo
    {
        return $this->belongsTo(BhkType::class, 'bhk_type_id');
    }

    public function bhk(): BelongsTo
    {
        return $this->bhkType();
    }

    public function propertyType(): BelongsTo
    {
        return $this->belongsTo(PropertyType::class, 'property_type_id');
    }

    public function flooringType(): BelongsTo
    {
        return $this->belongsTo(FlooringType::class, 'flooring_type_id');
    }

    public function flooring(): BelongsTo
    {
        return $this->flooringType();
    }

    public function furnishing(): BelongsTo
    {
        return $this->furnishingType();
    }

    public function coverPhoto(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PropertyPhoto::class)->where('is_featured', true);
    }

    public function coverImage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->coverPhoto();
    }

    public function currentPricing(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PropertyPricingVersion::class)->latestOfMany();
    }

    public function getRentAttribute(): float
    {
        return (float) ($this->currentPricing?->rent ?? 0);
    }

    public function getSecurityDepositAttribute(): float
    {
        return (float) ($this->currentPricing?->security_deposit ?? 0);
    }

    public function getSocietyFeeAttribute(): float
    {
        return (float) ($this->currentPricing?->society_fee ?? 0);
    }

    public function getBookingAmountAttribute(): float
    {
        return (float) ($this->currentPricing?->booking_amount ?? 0);
    }

    public function getFloorSpaceAttribute(): ?int
    {
        return $this->floor_space_sqft;
    }

    public function getFloorsAttribute(): ?int
    {
        return $this->floor;
    }

    public function getAddressAttribute(): string
    {
        return trim($this->address_line_1 . ' ' . $this->address_line_2);
    }

    public function isAvailable(): bool
    {
        return (bool) $this->is_listed && (!$this->available_from || $this->available_from <= now());
    }

    public function getNoOfRooms(string $name): int
    {
        return $this->rooms->filter(function ($room) use ($name) {
            $defName = $room->roomDefinition?->name ?? '';
            $custName = $room->custom_name ?? '';
            return stripos($defName, $name) !== false || stripos($custName, $name) !== false;
        })->count();
    }

    public function getNoOfFurnitures(string $name): int
    {
        return (int) $this->inventories->filter(function ($inv) use ($name) {
            return stripos($inv->inventoryType?->name ?? '', $name) !== false;
        })->sum('count');
    }

    public function checkPropertyAmenity(string $name): bool
    {
        return $this->amenities->contains(function ($amenity) use ($name) {
            return stripos($amenity->amenityType?->name ?? '', $name) !== false;
        });
    }

    public function getCityIdAttribute(): ?string
    {
        if ($this->locality_id) {
            $locality = Locality::find($this->locality_id);
            if ($locality?->city_id) {
                return $locality->city_id;
            }
        }

        $mouCityId = $this->mous()->latest()->first()?->legal_terms['city_id'] ?? null;
        if ($mouCityId) {
            return $mouCityId;
        }

        if ($this->city) {
            return \App\Domain\Geographic\Models\City::where('name', 'LIKE', $this->city)->value('id');
        }

        return null;
    }

    public function agreements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Agreement\Models\TenancyAgreement::class);
    }

    public function tenancyAgreements(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->agreements();
    }

    public function rooms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyRoom::class);
    }

    public function inventories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyInventory::class);
    }

    public function furnitures(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->inventories();
    }

    public function amenities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyAmenity::class);
    }

    public function establishments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyEstablishment::class);
    }

    public function nearbyEstablishments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->establishments();
    }

    public function pricingVersions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyPricingVersion::class);
    }

    public function financialTerms(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyFinancialTerm::class);
    }

    public function photos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyPhoto::class);
    }

    public function propertyImages(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->photos();
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyDocument::class);
    }

    public function utilities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyUtility::class);
    }



    public function mous(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Mou\Models\Mou::class, 'property_id');
    }

    public function furnishingType(): BelongsTo
    {
        return $this->belongsTo(FurnishingType::class, 'furnishing_type_id');
    }

    public function onboardingProject(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(OnboardingProject::class, 'property_id');
    }

    public function audits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Audit\Models\Audit::class);
    }

    public function maintenanceRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MaintenanceRequest::class);
    }

    public function openMaintenanceRequests(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MaintenanceRequest::class)
            ->whereNotIn('status', [
                MaintenanceStatus::RESOLVED,
                MaintenanceStatus::CLOSED,
                MaintenanceStatus::CANCELLED,
            ]);
    }

    public function scopeWithOpenMaintenance(Builder $query): Builder
    {
        return $query->whereHas('maintenanceRequests', function (Builder $q) {
            $q->whereNotIn('status', [
                MaintenanceStatus::RESOLVED,
                MaintenanceStatus::CLOSED,
                MaintenanceStatus::CANCELLED,
            ]);
        });
    }

    public function ownerPayouts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Finance\Models\OwnerPayout::class, 'property_id');
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Domain\Task\Models\Task::class, 'property_id');
    }

    public function owner(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            \App\Domain\Party\Models\Party::class,
            \App\Domain\Mou\Models\Mou::class,
            'property_id',
            'id',
            'id',
            'party_id'
        );
    }

    public function isLockedDuringOnboarding(): bool
    {
        return $this->mous()->where('type', \App\Domain\Mou\Enums\MouType::ONBOARDING)->exists() 
            && $this->onboardingProject?->status !== 'Activated';
    }

    public function getInvoicesQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $propertyId = $this->id;
        $propertyCode = $this->code;
        $agrmntIds = $this->agreements()->pluck('id')->toArray();
        $maintIds = $this->maintenanceRequests()->pluck('id')->toArray();
        $deboardIds = ! empty($agrmntIds)
            ? \App\Domain\Agreement\Models\TenantDeboarding::whereIn('tenancy_agreement_id', $agrmntIds)->pluck('id')->toArray()
            : [];

        return \Tek2991\Accounting\Models\Invoice::query()->where(function ($query) use ($propertyId, $propertyCode, $agrmntIds, $maintIds, $deboardIds) {
            $query->where(function ($sub) use ($propertyId) {
                $sub->where('reference_type', static::class)
                    ->where('reference_id', $propertyId);
            });

            if (! empty($agrmntIds)) {
                $query->orWhere(function ($sub) use ($agrmntIds) {
                    $sub->where('reference_type', \App\Domain\Agreement\Models\TenancyAgreement::class)
                        ->whereIn('reference_id', $agrmntIds);
                });
            }

            if (! empty($maintIds)) {
                $query->orWhere(function ($sub) use ($maintIds) {
                    $sub->where('reference_type', \App\Domain\Maintenance\Models\MaintenanceRequest::class)
                        ->whereIn('reference_id', $maintIds);
                });
            }

            if (! empty($deboardIds)) {
                $query->orWhere(function ($sub) use ($deboardIds) {
                    $sub->where('reference_type', \App\Domain\Agreement\Models\TenantDeboarding::class)
                        ->whereIn('reference_id', $deboardIds);
                });
            }

            if (! empty($propertyCode)) {
                $query->orWhere('notes', 'like', "%{$propertyCode}%");
            }
        });
    }

    public function getBillsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $propertyId = $this->id;
        $propertyCode = $this->code;
        $maintIds = $this->maintenanceRequests()->pluck('id')->toArray();

        return \Tek2991\Accounting\Models\Bill::query()->where(function ($query) use ($propertyId, $propertyCode, $maintIds) {
            $query->where(function ($sub) use ($propertyId) {
                $sub->where('reference_type', static::class)
                    ->where('reference_id', $propertyId);
            });

            if (! empty($maintIds)) {
                $query->orWhere(function ($sub) use ($maintIds) {
                    $sub->where('reference_type', \App\Domain\Maintenance\Models\MaintenanceRequest::class)
                        ->whereIn('reference_id', $maintIds);
                });
            }

            if (! empty($propertyCode)) {
                $query->orWhere('notes', 'like', "%{$propertyCode}%")
                    ->orWhere('vendor_reference', 'like', "%{$propertyCode}%");
            }
        });
    }

    public function getFinancialSummary(): array
    {
        $invoicesQuery = $this->getInvoicesQuery();
        $totalInvoiced = (float) ((clone $invoicesQuery)->sum('grand_total') / 100);
        $totalPaidInvoices = (float) ((clone $invoicesQuery)->sum('amount_paid') / 100);
        $balanceDueInvoices = (float) ((clone $invoicesQuery)->sum('balance_due') / 100);
        $invoicesCount = (clone $invoicesQuery)->count();

        $billsQuery = $this->getBillsQuery();
        $totalBills = (float) ((clone $billsQuery)->sum('grand_total') / 100);
        $totalPaidBills = (float) ((clone $billsQuery)->sum('amount_paid') / 100);
        $balanceDueBills = (float) ((clone $billsQuery)->sum('balance_due') / 100);
        $billsCount = (clone $billsQuery)->count();

        return [
            'total_invoiced' => $totalInvoiced,
            'total_collected' => $totalPaidInvoices,
            'receivables_due' => $balanceDueInvoices,
            'invoices_count' => $invoicesCount,
            'total_bills' => $totalBills,
            'bills_paid' => $totalPaidBills,
            'payables_due' => $balanceDueBills,
            'bills_count' => $billsCount,
        ];
    }
}