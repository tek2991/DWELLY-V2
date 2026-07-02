<?php

namespace App\Domain\Party\Services;

use App\Domain\Party\Models\Party;
use Illuminate\Support\Collection;
use Illuminate\Database\Eloquent\Builder;

class IdentityResolutionService
{
    /**
     * Find potential duplicate parties based on unique identifiers.
     * 
     * @param array $data Data containing potential identifiers (phone, email, pan_number, aadhaar_number, gstin)
     * @return Collection Collection of matched Party models
     */
    public function findPotentialDuplicates(array $data): Collection
    {
        return Party::query()
            ->where(function (Builder $query) use ($data) {
                if (!empty($data['phone'])) {
                    $query->orWhere('phone', $data['phone']);
                }
                
                if (!empty($data['email'])) {
                    $query->orWhere('email', $data['email']);
                }
                
                if (!empty($data['pan_number']) || !empty($data['aadhaar_number'])) {
                    $query->orWhereHas('individual', function (Builder $q) use ($data) {
                        if (!empty($data['pan_number'])) {
                            $q->where('pan_number', $data['pan_number']);
                        }
                        if (!empty($data['aadhaar_number'])) {
                            $q->orWhere('aadhaar_number', $data['aadhaar_number']);
                        }
                    });
                }
                
                if (!empty($data['pan_number']) || !empty($data['gstin'])) {
                    $query->orWhereHas('organization', function (Builder $q) use ($data) {
                        if (!empty($data['pan_number'])) {
                            $q->where('pan', $data['pan_number']); // note: column name in organization is 'pan'
                        }
                        if (!empty($data['gstin'])) {
                            $q->orWhere('gstin', $data['gstin']);
                        }
                    });
                }
            })
            ->get();
    }
}
