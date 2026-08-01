<?php

namespace App\Domain\Party\Services;

use App\Domain\Finance\Services\AccountingProvisioningService;
use App\Domain\Party\Enums\VendorOnboardingStatus;
use App\Domain\Party\Models\Party;
use App\Domain\Party\Models\VendorProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class VendorOnboardingService
{
    public function submitForVerification(VendorProfile $profile): VendorProfile
    {
        $profile->update([
            'onboarding_status' => VendorOnboardingStatus::PENDING_VERIFICATION,
        ]);

        return $profile;
    }

    public function verifyVendor(VendorProfile $profile, ?User $verifier = null, ?string $notes = null): VendorProfile
    {
        DB::transaction(function () use ($profile, $verifier, $notes) {
            $profile->update([
                'onboarding_status' => VendorOnboardingStatus::VERIFIED,
                'verification_notes' => $notes,
                'verified_at' => now(),
                'verified_by_id' => $verifier?->id ?? auth()->id(),
            ]);

            // Ensure accounting integration is ready for the vendor party
            if ($profile->party) {
                app(AccountingProvisioningService::class)->ensurePartyAccountingReady($profile->party);
            }
        });

        return $profile;
    }

    public function rejectVendor(VendorProfile $profile, string $reason): VendorProfile
    {
        $profile->update([
            'onboarding_status' => VendorOnboardingStatus::REJECTED,
            'verification_notes' => $reason,
        ]);

        return $profile;
    }

    public function suspendVendor(VendorProfile $profile, ?string $reason = null): VendorProfile
    {
        $profile->update([
            'onboarding_status' => VendorOnboardingStatus::SUSPENDED,
            'verification_notes' => $reason,
        ]);

        return $profile;
    }
}
