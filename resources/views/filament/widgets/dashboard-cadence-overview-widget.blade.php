<x-filament-widgets::widget style="grid-column: 1 / -1;">
    @php
        $leases = $this->getLeaseExpirationsData();
        $cadence = $this->getCadenceData();
    @endphp

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1rem;">
        
        <!-- CARD 1: Expiring Lease Agreements Outlook -->
        <x-filament::section>
            <x-slot name="heading">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 1.75rem; height: 1.75rem; border-radius: 0.375rem; background: #ede9fe; color: #7c3aed; display: flex; align-items: center; justify-content: center;">
                            <x-filament::icon icon="heroicon-m-clock" style="width: 1rem; height: 1rem;" />
                        </div>
                        <span style="font-weight: 700; font-size: 0.9375rem; color: #0f172a;">Lease Expirations & Renewals</span>
                    </div>
                    <a href="{{ url('/operations/operations-dashboard?tab=renewals') }}" 
                       style="font-size: 0.75rem; font-weight: 700; color: #7c3aed; text-decoration: none; display: flex; align-items: center; gap: 0.25rem;">
                        <span>Renewals Console</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </x-slot>

            <div style="display: flex; flex-direction: column; gap: 0.875rem;">
                
                <!-- Quick KPI strip -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.625rem 0.75rem;">
                    <div>
                        <div style="font-size: 0.625rem; font-weight: 700; color: #991b1b; text-transform: uppercase;">&lt; 30 Days</div>
                        <div style="font-size: 1.125rem; font-weight: 800; color: #dc2626; margin-top: 0.125rem;">
                            {{ $leases['critical_count'] }} <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b;">leases</span>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.625rem; font-weight: 700; color: #92400e; text-transform: uppercase;">31–60 Days</div>
                        <div style="font-size: 1.125rem; font-weight: 800; color: #d97706; margin-top: 0.125rem;">
                            {{ $leases['upcoming_count'] }} <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b;">leases</span>
                        </div>
                    </div>
                    <div>
                        <div style="font-size: 0.625rem; font-weight: 700; color: #6d28d9; text-transform: uppercase;">Rent at Risk</div>
                        <div style="font-size: 1.125rem; font-weight: 800; color: #7c3aed; margin-top: 0.125rem;">
                            ₹{{ number_format($leases['rent_at_risk'], 0) }}
                        </div>
                    </div>
                </div>

                <!-- Imminent Expiring Leases Preview -->
                <div>
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 0.375rem;">
                        Imminent Expirations Pipeline
                    </div>

                    @if ($leases['imminent_leases']->isEmpty())
                        <div style="background: #f0fdf4; border: 1px dashed #86efac; border-radius: 0.5rem; padding: 1rem; text-align: center;">
                            <div style="font-size: 0.75rem; font-weight: 700; color: #166534;">No Active Leases Expiring Within 90 Days</div>
                            <div style="font-size: 0.6875rem; color: #15803d; margin-top: 0.125rem;">Your tenant portfolio is currently secured with active commitments.</div>
                        </div>
                    @else
                        <div style="display: flex; flex-direction: column; gap: 0.375rem;">
                            @foreach ($leases['imminent_leases'] as $agr)
                                @php
                                    $tenant = $agr->tenants->first();
                                    $endDate = \Carbon\Carbon::parse($agr->end_date);
                                    $diffDays = (int) now()->diffInDays($endDate, false);
                                @endphp
                                <div style="display: flex; align-items: center; justify-content: space-between; background: #ffffff; border: 1px solid #f1f5f9; border-radius: 0.5rem; padding: 0.5rem 0.625rem;">
                                    <div style="min-width: 0; flex: 1;">
                                        <div style="font-size: 0.75rem; font-weight: 700; color: #0f172a; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            {{ $agr->property?->building_name ?? 'Property' }} 
                                            <span style="font-weight: 500; color: #64748b;">• {{ $tenant?->display_name ?? 'Tenant' }}</span>
                                        </div>
                                        <div style="font-size: 0.6875rem; color: #64748b;">
                                            ₹{{ number_format($agr->rent_amount, 0) }}/mo • Ends {{ $endDate->format('d M Y') }}
                                        </div>
                                    </div>
                                    <div style="flex-shrink: 0; margin-left: 0.5rem;">
                                        @if ($diffDays <= 30)
                                            <span style="font-size: 0.6875rem; font-weight: 700; background: #fef2f2; color: #dc2626; padding: 0.125rem 0.4375rem; border-radius: 9999px;">
                                                {{ $diffDays }}d left
                                            </span>
                                        @else
                                            <span style="font-size: 0.6875rem; font-weight: 700; background: #fffbeb; color: #d97706; padding: 0.125rem 0.4375rem; border-radius: 9999px;">
                                                {{ $diffDays }}d left
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>
        </x-filament::section>

        <!-- CARD 2: Billing & Disbursal Cadence & Schedule -->
        <x-filament::section>
            <x-slot name="heading">
                <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <div style="width: 1.75rem; height: 1.75rem; border-radius: 0.375rem; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center;">
                            <x-filament::icon icon="heroicon-m-arrow-path" style="width: 1rem; height: 1rem;" />
                        </div>
                        <span style="font-weight: 700; font-size: 0.9375rem; color: #0f172a;">Billing & Payout Cadence</span>
                    </div>
                    <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                        Monthly Operational Cycle
                    </span>
                </div>
            </x-slot>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                
                <!-- 1. Rent Demands (AR) -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem 0.875rem; border-left: 4px solid #10b981;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <span style="font-size: 0.6875rem; font-weight: 700; color: #166534; text-transform: uppercase;">Rent Demands Cycle</span>
                                <span style="font-size: 0.625rem; font-weight: 700; background: {{ $cadence['rent']['status_bg'] }}; color: {{ $cadence['rent']['status_color'] }}; padding: 0.1rem 0.375rem; border-radius: 9999px;">
                                    {{ $cadence['rent']['status_label'] }}
                                </span>
                            </div>
                            <div style="font-size: 0.75rem; color: #334155; margin-top: 0.25rem;">
                                @if ($cadence['rent']['last_generated_at'])
                                    Last run: <strong>{{ \Carbon\Carbon::parse($cadence['rent']['last_generated_at'])->format('d M Y') }}</strong>
                                    @if ($cadence['rent']['last_amount'])
                                        (₹{{ number_format($cadence['rent']['last_amount'], 0) }})
                                    @endif
                                @else
                                    Last run: <em>No cycle generated yet</em>
                                @endif
                            </div>
                            <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.125rem;">
                                Recommended Next Run: <strong style="color: #0f172a;">{{ $cadence['rent']['recommended_date'] }}</strong> 
                                @if ($cadence['rent']['days_remaining'] > 0)
                                    <span style="color: #059669;">(in {{ $cadence['rent']['days_remaining'] }} days)</span>
                                @elseif ($cadence['rent']['days_remaining'] === 0)
                                    <span style="color: #d97706; font-weight: 700;">(due today!)</span>
                                @else
                                    <span style="color: #dc2626; font-weight: 700;">(overdue)</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ url('/operations/bulk-generate-monthly-rent') }}" 
                           style="flex-shrink: 0; font-size: 0.6875rem; font-weight: 700; background: #ecfdf5; color: #059669; border: 1px solid #a7f3d0; padding: 0.3125rem 0.625rem; border-radius: 0.375rem; text-decoration: none; display: flex; align-items: center; gap: 0.25rem; transition: background 0.15s ease;">
                            <span>Run Demands</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>

                <!-- 2. Owner Payouts (AP) -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem 0.875rem; border-left: 4px solid #3b82f6;">
                    <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 0.5rem;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.375rem;">
                                <span style="font-size: 0.6875rem; font-weight: 700; color: #1e40af; text-transform: uppercase;">Owner Payouts Disbursal</span>
                                <span style="font-size: 0.625rem; font-weight: 700; background: {{ $cadence['payouts']['status'] === 'Active Window' ? '#ecfdf5' : '#eff6ff' }}; color: {{ $cadence['payouts']['status'] === 'Active Window' ? '#059669' : '#2563eb' }}; padding: 0.1rem 0.375rem; border-radius: 9999px;">
                                    {{ $cadence['payouts']['status_text'] }}
                                </span>
                            </div>
                            <div style="font-size: 0.75rem; color: #334155; margin-top: 0.25rem;">
                                @if ($cadence['payouts']['last_disbursed_at'])
                                    Last batch: <strong>{{ \Carbon\Carbon::parse($cadence['payouts']['last_disbursed_at'])->format('d M Y') }}</strong>
                                    @if ($cadence['payouts']['last_amount'])
                                        (₹{{ number_format($cadence['payouts']['last_amount'], 0) }})
                                    @endif
                                @else
                                    Last batch: <em>No disbursements recorded</em>
                                @endif
                            </div>
                            <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.125rem;">
                                Recommended Window: <strong style="color: #0f172a;">{{ $cadence['payouts']['recommended_window'] }}</strong>
                                @if ($cadence['payouts']['pending_count'] > 0)
                                    • <span style="color: #2563eb; font-weight: 700;">₹{{ number_format($cadence['payouts']['pending_total'], 0) }} in queue ({{ $cadence['payouts']['pending_count'] }} pending)</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ url('/operations/bulk-generate-owner-payouts') }}" 
                           style="flex-shrink: 0; font-size: 0.6875rem; font-weight: 700; background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; padding: 0.3125rem 0.625rem; border-radius: 0.375rem; text-decoration: none; display: flex; align-items: center; gap: 0.25rem; transition: background 0.15s ease;">
                            <span>Disburse</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>

            </div>
        </x-filament::section>

    </div>
</x-filament-widgets::widget>
