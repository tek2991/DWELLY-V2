<?php

namespace App\Domain\Property\Models;

use App\Domain\Shared\Models\DomainModel;
use App\Domain\Shared\Traits\BelongsToBranch;
use App\Domain\Geographic\Models\Locality;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

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

    public function amenities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyAmenity::class);
    }

    public function establishments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PropertyEstablishment::class);
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
        return $this->hasMany(\App\Domain\Maintenance\Models\MaintenanceRequest::class);
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