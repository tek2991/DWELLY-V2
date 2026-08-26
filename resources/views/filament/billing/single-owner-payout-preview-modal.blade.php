@php
    $propertyId = isset($get) ? $get('property_id') : null;
    $month = isset($get) ? (int) ($get('month') ?: date('n')) : (int) date('n');
    $year = isset($get) ? (int) ($get('year') ?: date('Y')) : (int) date('Y');

    $property = $propertyId ? \App\Domain\Property\Models\Property::with(['owner.bankAccounts', 'agreements' => fn($q) => $q->where('status', 'active')])->find($propertyId) : null;
    $calc = $property ? app(\App\Domain\Finance\Services\OwnerPayoutService::class)->calculatePayoutDetails($property, $month, $year) : null;
@endphp

@if($calc && $calc['eligible'])
    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.875rem; font-size: 0.8125rem;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.625rem; flex-wrap: wrap; gap: 0.5rem;">
            <div style="font-weight: 700; color: #1e293b; font-size: 0.875rem; display: flex; align-items: center; gap: 0.375rem;">
                <span>📅</span>
                <span>Calculated Payout Period: <span style="color: #2563eb;">{{ $calc['formatted_period'] }}</span></span>
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

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.5rem; background: #ffffff; padding: 0.625rem 0.75rem; border-radius: 0.375rem; border: 1px solid #e2e8f0; font-size: 0.75rem;">
            <div>
                <span style="color: #64748b; font-size: 0.6875rem;">Key Handover Date:</span>
                <div style="font-weight: 600; color: #0f172a;">{{ $calc['handover_date_formatted'] }}</div>
            </div>
            <div>
                <span style="color: #64748b; font-size: 0.6875rem;">Gross Rent Demand:</span>
                <div style="font-weight: 700; color: #0f172a;">₹{{ number_format($calc['gross_rent'], 2) }}</div>
            </div>
            <div>
                <span style="color: #64748b; font-size: 0.6875rem;">Mgmt Commission:</span>
                <div style="font-weight: 600; color: #dc2626;">-₹{{ number_format($calc['management_fee'], 2) }}</div>
            </div>
            <div>
                <span style="color: #64748b; font-size: 0.6875rem;">Advance / Maint. Offset:</span>
                <div style="font-weight: 600; color: #d97706;">-₹{{ number_format($calc['advance_offset'], 2) }}</div>
            </div>
            <div>
                <span style="color: #64748b; font-size: 0.6875rem;">Suggested Net Payout:</span>
                <div style="font-weight: 700; color: #15803d;">₹{{ number_format($calc['net_payout'], 2) }}</div>
            </div>
        </div>

        @if(!empty($calc['maintenance_invoices']))
            <div style="margin-top: 0.5rem; background: #fffdf5; border: 1px solid #fde68a; border-radius: 0.375rem; padding: 0.5rem 0.75rem; font-size: 0.6875rem;">
                <div style="font-weight: 700; color: #92400e; margin-bottom: 0.25rem;">
                    🔧 Unpaid Maintenance Invoices to Auto-Settle ({{ count($calc['maintenance_invoices']) }}):
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.375rem;">
                    @foreach($calc['maintenance_invoices'] as $m)
                        <span style="background: #ffffff; border: 1px solid #fde68a; padding: 0.15rem 0.375rem; border-radius: 0.25rem; color: #78350f;">
                            <strong>{{ $m['ticket_number'] }}</strong>: {{ $m['title'] }} (₹{{ number_format($m['amount'], 2) }})
                        </span>
                    @endforeach
                </div>
            </div>
        @endif

        <div style="margin-top: 0.5rem; font-size: 0.6875rem; color: #64748b; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.25rem;">
            <div>
                🏦 <strong>Owner Bank:</strong> {{ $calc['bank_details_formatted'] }}
            </div>
            @if($calc['is_prorated'])
                <div>
                    💡 <em>Prorated formula applied for {{ $calc['days_active'] }} days active.</em>
                </div>
            @endif
        </div>
    </div>
@endif
