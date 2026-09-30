<?php

namespace App\Domain\Party\Services;

use App\Domain\Party\Models\Party;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class IdentityResolutionService
{
    /**
     * Find potential duplicate parties based on unique identifiers.
     *
     * @param  array  $data  Data containing potential identifiers (phone, email, pan_number, aadhaar_number, gstin)
     * @return Collection Collection of matched Party models
     */
    public function findPotentialDuplicates(array $data): Collection
    {
        return Party::query()
            ->where(function (Builder $query) use ($data) {
                if (! empty($data['phone'])) {
                    $query->orWhere('phone', $data['phone']);
                }

                if (! empty($data['email'])) {
                    $query->orWhere('email', $data['email']);
                }

                if (! empty($data['pan_number']) || ! empty($data['aadhaar_number'])) {
                    $query->orWhereHas('individual', function (Builder $q) use ($data) {
                        if (! empty($data['pan_number'])) {
                            $q->where('pan_number', $data['pan_number']);
                        }
                        if (! empty($data['aadhaar_number'])) {
                            $q->orWhere('aadhaar_number', $data['aadhaar_number']);
                        }
                    });
                }

                if (! empty($data['pan_number']) || ! empty($data['gstin'])) {
                    $query->orWhereHas('organization', function (Builder $q) use ($data) {
                        if (! empty($data['pan_number'])) {
                            $q->where('pan', $data['pan_number']); // note: column name in organization is 'pan'
                        }
                        if (! empty($data['gstin'])) {
                            $q->orWhere('gstin', $data['gstin']);
                        }
                    });
                }
            })
            ->get();
    }

    /**
     * Find an existing Party by its normalized PAN number across individual and organization profiles.
     */
    public function findPartyByPan(?string $pan): ?Party
    {
        if (empty($pan)) {
            return null;
        }

        $normalized = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', trim($pan)));
        if (strlen($normalized) !== 10) {
            return null;
        }

        return Party::query()
            ->where(function (Builder $query) use ($normalized) {
                $query->whereHas('individual', fn (Builder $q) => $q->where('pan_number', $normalized))
                    ->orWhereHas('organization', fn (Builder $q) => $q->where('pan', $normalized));
            })
            ->first();
    }
}
