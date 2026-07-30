<?php

namespace App\Domain\Agreement\Actions;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Agreement\Services\TenancyAgreementPdfService;
use App\Domain\Agreement\Services\TenancyAgreementDocxService;
use App\Domain\Audit\Models\Audit;
use App\Domain\Audit\Enums\AuditType;
use App\Domain\Audit\Enums\AuditStatus;
use App\Domain\Property\Models\Property;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Domain\Shared\Services\NumberingService;
use App\Domain\Finance\Services\CalculationService;

class DraftTenancyAgreementAction
{
    public function __construct(
        private CalculationService $calculationService,
        private TenancyAgreementPdfService $pdfService,
        private TenancyAgreementDocxService $docxService
    ) {}

    public function execute(Property $property, array $agreementData, array $tenantRoles, User $initiator): TenancyAgreement
    {
        return DB::transaction(function () use ($property, $agreementData, $tenantRoles, $initiator) {
            
            // 1. Generate code using NumberingService
            if (empty($agreementData['code'])) {
                try {
                    $agreementData['code'] = NumberingService::generate('tenancy');
                } catch (\Throwable $e) {
                    $year = date('Y');
                    $latestCount = TenancyAgreement::where('code', 'like', "TNC-{$year}-%")->count();
                    $agreementData['code'] = 'TNC-' . $year . '-' . str_pad($latestCount + 1, 5, '0', STR_PAD_LEFT);
                }
            }
            
            // 2. Auto-fetch Rent & Security Deposit from Property Pricing Model if missing
            $latestPricing = $property->pricingVersions()->latest('effective_from')->first();

            if (empty($agreementData['rent_amount'])) {
                $agreementData['rent_amount'] = $latestPricing?->rent ?? 0.00;
            }
            if (empty($agreementData['security_deposit'])) {
                $agreementData['security_deposit'] = $latestPricing?->security_deposit ?? (($agreementData['rent_amount'] ?? 0) * 2);
            }
            if (empty($agreementData['pricing_version_id']) && $latestPricing) {
                $agreementData['pricing_version_id'] = $latestPricing->id;
            }
            
            // 3. Set default initial status
            $agreementData['status'] = $agreementData['status'] ?? 'draft';
            $agreementData['property_id'] = $property->id;

            // 4. Create the Tenancy Agreement
            $agreement = TenancyAgreement::create($agreementData);

            // 5. Attach Tenants (Roles)
            $primaryTenantId = null;
            foreach ($tenantRoles as $roleData) {
                $role = $agreement->roles()->create([
                    'party_id' => $roleData['party_id'],
                    'role_type' => $roleData['role_type'], // e.g. Primary Tenant
                    'is_primary' => $roleData['is_primary'] ?? false,
                ]);

                if ($role->is_primary) {
                    $primaryTenantId = $role->party_id;
                }
            }

            // 6. Automatically Trigger Move-In Audit linked to Property & Tenant
            if (empty($agreement->audit_id)) {
                $latestApprovedAudit = Audit::where('property_id', $property->id)
                    ->whereIn('status', [AuditStatus::APPROVED, AuditStatus::COMPLETED])
                    ->latest()
                    ->first();

                $moveInAudit = Audit::create([
                    'property_id' => $property->id,
                    'tenant_id' => $primaryTenantId,
                    'audit_type' => AuditType::MOVE_IN,
                    'status' => AuditStatus::DRAFT,
                    'reference_audit_id' => $latestApprovedAudit?->id,
                    'notes' => 'Auto-triggered Move-In Audit for Tenancy Agreement ' . $agreement->code,
                ]);

                $agreement->audit_id = $moveInAudit->id;
                $agreement->save();
            } else {
                // Link tenant to existing selected audit if not already linked
                $audit = Audit::find($agreement->audit_id);
                if ($audit && empty($audit->tenant_id) && $primaryTenantId) {
                    $audit->tenant_id = $primaryTenantId;
                    $audit->save();
                }
            }

            // 7. Generate initial draft PDF and Word documents
            try {
                $this->pdfService->saveDraftPdf($agreement);
                $this->docxService->saveDraftDocx($agreement);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Failed to generate initial agreement draft media: ' . $e->getMessage());
            }

            return $agreement;
        });
    }
}


