<?php

namespace App\Domain\Agreement\Actions;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Services\TenancyAgreementPdfService;
use App\Domain\Agreement\Services\TenancyAgreementDocxService;
use App\Domain\Shared\Services\NumberingService;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RenewTenancyAgreementAction
{
    public function __construct(
        private TenancyAgreementPdfService $pdfService,
        private TenancyAgreementDocxService $docxService
    ) {}

    /**
     * Renew an existing tenancy agreement.
     *
     * @param TenancyAgreement $previousAgreement
     * @param array $overrides
     * @param User|null $actor
     * @return TenancyAgreement
     */
    public function execute(TenancyAgreement $previousAgreement, array $overrides = [], ?User $actor = null): TenancyAgreement
    {
        return DB::transaction(function () use ($previousAgreement, $overrides, $actor) {
            // 1. Calculate default dates:
            // start_date = previous end_date + 1 day (or tomorrow if no end_date)
            $defaultStartDate = $previousAgreement->end_date 
                ? Carbon::parse($previousAgreement->end_date)->addDay() 
                : Carbon::today();
            $startDate = !empty($overrides['start_date']) 
                ? Carbon::parse($overrides['start_date']) 
                : $defaultStartDate;

            // end_date = start_date + 11 months - 1 day (standard 11-month lease)
            $defaultEndDate = (clone $startDate)->addMonths(11)->subDay();
            $endDate = !empty($overrides['end_date']) 
                ? Carbon::parse($overrides['end_date']) 
                : $defaultEndDate;

            // Rent: Use override, or support optional escalation, or keep current rent
            $rentAmount = isset($overrides['rent_amount']) 
                ? (float) $overrides['rent_amount'] 
                : (float) $previousAgreement->rent_amount;

            $firstMonthRent = isset($overrides['first_month_rent']) 
                ? (float) $overrides['first_month_rent'] 
                : $rentAmount;

            // Security deposit carried forward from previous agreement
            $securityDeposit = isset($overrides['security_deposit']) 
                ? (float) $overrides['security_deposit'] 
                : (float) $previousAgreement->security_deposit;

            // Documentation fee default for renewal is 1000.00
            $documentationCharge = isset($overrides['documentation_charge']) 
                ? (float) $overrides['documentation_charge'] 
                : 1000.00;

            // 2. Generate code using NumberingService
            try {
                $code = NumberingService::generate('tenancy');
            } catch (\Throwable $e) {
                $year = date('Y');
                $seq = TenancyAgreement::where('code', 'like', "TNC-{$year}-%")->count() + 1;
                do {
                    $code = 'TNC-' . $year . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
                    $seq++;
                } while (TenancyAgreement::where('code', $code)->exists());
            }

            // 3. Create the renewal agreement carrying forward essential metadata
            $renewalAgreement = TenancyAgreement::create([
                'branch_id' => $previousAgreement->branch_id,
                'property_id' => $previousAgreement->property_id,
                'audit_id' => $previousAgreement->audit_id, // Carry forward move-in audit reference to preserve Annexure III inventory
                'previous_agreement_id' => $previousAgreement->id,
                'is_renewal' => true,
                'renewal_notes' => $overrides['renewal_notes'] ?? "Renewed from {$previousAgreement->code}",
                'code' => $code,
                'status' => 'draft',
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'rent_amount' => $rentAmount,
                'first_month_rent' => $firstMonthRent,
                'first_month_rent_notes' => $overrides['first_month_rent_notes'] ?? 'Renewal term standard first month rent',
                'security_deposit' => $securityDeposit,
                'security_deposit_notes' => $overrides['security_deposit_notes'] ?? "Carried forward from previous agreement {$previousAgreement->code}",
                'booking_amount' => 0.00,
                'documentation_charge' => $documentationCharge,
                'lock_in_period_months' => $overrides['lock_in_period_months'] ?? $previousAgreement->lock_in_period_months ?? 0,
                'notice_period_days' => $overrides['notice_period_days'] ?? $previousAgreement->notice_period_days ?? 30,
                'special_terms' => $overrides['special_terms'] ?? $previousAgreement->special_terms,
                'apdcl_consumer_id' => $previousAgreement->apdcl_consumer_id,
                'electricity_provider_id' => $previousAgreement->electricity_provider_id,
                'tenant_bank_details' => $previousAgreement->tenant_bank_details,
                'secondary_tenants' => $previousAgreement->secondary_tenants,
                'pricing_version_id' => $previousAgreement->pricing_version_id,
                'keys_handed_over' => true, // Tenant already possesses property keys
                'keys_handed_over_at' => $previousAgreement->keys_handed_over_at ?? now(),
                'key_handover_notes' => 'Keys retained from previous tenancy agreement ' . $previousAgreement->code,
                'key_details' => $previousAgreement->key_details,
            ]);

            // 4. Carry forward Tenant roles
            foreach ($previousAgreement->roles as $role) {
                $renewalAgreement->roles()->create([
                    'party_id' => $role->party_id,
                    'role_type' => $role->role_type,
                    'is_primary' => $role->is_primary,
                ]);
            }

            // 5. Copy KYC media from previous agreement to renewal agreement
            $kycCollections = [
                'tenant_aadhaar',
                'tenant_pan',
                'tenant_photo',
                'cancelled_cheque',
                'kyc_documents',
                'secondary_tenant_kyc',
            ];

            foreach ($kycCollections as $collection) {
                foreach ($previousAgreement->getMedia($collection) as $media) {
                    try {
                        $media->copy($renewalAgreement, $collection);
                    } catch (\Throwable $e) {
                        Log::warning("Failed copying {$collection} media to renewal agreement: " . $e->getMessage());
                    }
                }
            }

            // 6. Generate initial draft PDF and Word documents
            try {
                $this->pdfService->saveDraftPdf($renewalAgreement);
                $this->docxService->saveDraftDocx($renewalAgreement);
            } catch (\Throwable $e) {
                Log::warning('Failed generating initial renewal draft documents: ' . $e->getMessage());
            }

            return $renewalAgreement;
        });
    }
}
