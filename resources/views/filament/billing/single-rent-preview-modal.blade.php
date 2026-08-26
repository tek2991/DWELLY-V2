@php
    $agreementId = isset($get) ? $get('tenancy_agreement_id') : null;
    $month = isset($get) ? (int) ($get('month') ?: date('n')) : (int) date('n');
    $year = isset($get) ? (int) ($get('year') ?: date('Y')) : (int) date('Y');

    $agreement = $agreementId ? \App\Domain\Agreement\Models\TenancyAgreement::with(['property.owner', 'roles.party'])->find($agreementId) : null;
    $calc = $agreement ? app(\App\Domain\Finance\Services\RentBillingService::class)->calculateBillingDetails($agreement, $month, $year) : null;
@endphp

@if($calc)
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.875rem; font-size: 0.8125rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.625rem; flex-wrap: wrap; gap: 0.5rem;">
            <div style="font-weight: 700; color: #1e293b; font-size: 0.875rem; display: flex; align-items: center; gap: 0.375rem;">
                <span>📅</span>
                <span>Calculated Billing Period: <span style="color: #2563eb;">{{ $calc['formatted_period'] }}</span></span>
            </div>

            @if($calc['is_prorated'])
                <span style="background: #fef3c7; color: #92400e; font-size: 0.6875rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 9999px; border: 1px solid #fde68a;">
                    ★ Mid-Month Prorated ({{ $calc['days_active'] }} / {{ $calc['total_days_in_month'] }} Days)
                </span>
            @elseif($calc['is_first_month'])
                <span style="background: #e0f2fe; color: #0369a1; font-size: 0.6875rem; font-weight: 700; padding: 0.15rem 0.5rem; border-radius: 9999px;">
                    1st Month (Full)
                </span>
            @else
                <span style="background: #f1f5f9; color: #475569; font-size: 0.6875rem; font-weight: 600; padding: 0.15rem 0.5rem; border-radius: 9999px;">
                    Standard Monthly Cycle
                </span>
            @endif
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 0.5rem; background: #ffffff; padding: 0.625rem 0.75rem; border-radius: 0.375rem; border: 1px solid #e2e8f0; font-size: 0.75rem;">
            <div>
                <span style="color: #64748b; font-size: 0.6875rem;">Key Handover Date:</span>
                <div style="font-weight: 600; color: #0f172a;">{{ $calc['handover_date_formatted'] }}</div>
            </div>
            <div>
                <span style="color: #64748b; font-size: 0.6875rem;">Standard Monthly Rent:</span>
                <div style="font-weight: 600; color: #0f172a;">₹{{ number_format($calc['standard_rent'], 2) }}</div>
            </div>
            <div>
                <span style="color: #64748b; font-size: 0.6875rem;">Calculated Rent Demand:</span>
                <div style="font-weight: 700; color: #16a34a;">₹{{ number_format($calc['rent_amount'], 2) }}</div>
            </div>
        </div>

        @if($calc['is_prorated'])
            <div style="margin-top: 0.5rem; font-size: 0.6875rem; color: #64748b;">
                💡 <em>Proration breakdown: (₹{{ number_format($calc['standard_rent'], 2) }} / {{ $calc['total_days_in_month'] }} days) × {{ $calc['days_active'] }} days active = ₹{{ number_format($calc['rent_amount'], 2) }}</em>
            </div>
        @endif
    </div>
@endif
