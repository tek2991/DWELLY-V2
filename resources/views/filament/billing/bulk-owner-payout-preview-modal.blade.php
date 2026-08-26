@php
    $month = isset($get) ? (int) ($get('month') ?: date('n')) : (int) date('n');
    $year = isset($get) ? (int) ($get('year') ?: date('Y')) : (int) date('Y');
    $service = app(\App\Domain\Finance\Services\OwnerPayoutService::class);
    $preview = $service->getBulkPayoutPreview($month, $year);
    $summary = $preview['summary'];
    $items = $preview['items'];
@endphp

<div style="display: flex; flex-direction: column; gap: 1rem; width: 100%;">
    <!-- Top Summary Banner -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem;">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem 1rem;">
            <div style="font-size: 0.6875rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Payout Month</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">{{ $preview['month_name'] }}</div>
        </div>

        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.5rem; padding: 0.75rem 1rem;">
            <div style="font-size: 0.6875rem; color: #166534; font-weight: 600; text-transform: uppercase;">Ready to Disburse</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #15803d; margin-top: 0.25rem;">{{ $summary['ready_count'] }} <span style="font-size: 0.75rem; font-weight: 500; color: #4ade80;">/ {{ $summary['total_properties'] }}</span></div>
        </div>

        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.5rem; padding: 0.75rem 1rem;">
            <div style="font-size: 0.6875rem; color: #1e40af; font-weight: 600; text-transform: uppercase;">Net Disbursed Amount</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #1d4ed8; margin-top: 0.25rem;">₹{{ number_format($summary['total_net_payout'], 2) }}</div>
        </div>

        <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 0.5rem; padding: 0.75rem 1rem;">
            <div style="font-size: 0.6875rem; color: #6b21a8; font-weight: 600; text-transform: uppercase;">Mgmt Fee Revenue</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #7e22ce; margin-top: 0.25rem;">₹{{ number_format($summary['total_management_fee'], 2) }}</div>
        </div>

        @if(($summary['total_maintenance_offset'] ?? 0) > 0)
        <div style="background: #fffbeb; border: 1px solid #fde68a; border-radius: 0.5rem; padding: 0.75rem 1rem;">
            <div style="font-size: 0.6875rem; color: #92400e; font-weight: 600; text-transform: uppercase;">Maint. Invoices Offset</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #b45309; margin-top: 0.25rem;">₹{{ number_format($summary['total_maintenance_offset'], 2) }}</div>
        </div>
        @endif

        @if($summary['already_processed_count'] > 0)
        <div style="background: #fefce8; border: 1px solid #fef08a; border-radius: 0.5rem; padding: 0.75rem 1rem;">
            <div style="font-size: 0.6875rem; color: #854d0e; font-weight: 600; text-transform: uppercase;">Already Processed</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #a16207; margin-top: 0.25rem;">{{ $summary['already_processed_count'] }} (Skipped)</div>
        </div>
        @endif
    </div>

    <!-- Payouts Table Preview -->
    <div style="border: 1px solid #e2e8f0; border-radius: 0.5rem; overflow: hidden; background: #ffffff;">
        <div style="padding: 0.75rem 1rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between;">
            <span style="font-size: 0.8125rem; font-weight: 700; color: #1e293b;">
                Owner Payouts Breakdown ({{ count($items) }} active properties)
            </span>
            <span style="font-size: 0.75rem; color: #64748b;">
                Calculated based on Key Handover, Proration, Mgmt Fee (10%), Maintenance Offsets & Advances
            </span>
        </div>

        @if(empty($items))
            <div style="padding: 2rem; text-align: center; color: #64748b; font-size: 0.875rem;">
                No active properties found for this period.
            </div>
        @else
            <div style="max-height: 380px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.75rem;">
                    <thead>
                        <tr style="background: #f1f5f9; color: #475569; font-weight: 600; border-bottom: 1px solid #cbd5e1; position: sticky; top: 0; z-index: 1;">
                            <th style="padding: 0.5rem 0.75rem;">Property & Owner</th>
                            <th style="padding: 0.5rem 0.75rem;">Billing Period</th>
                            <th style="padding: 0.5rem 0.75rem; text-align: right;">Gross Rent</th>
                            <th style="padding: 0.5rem 0.75rem; text-align: right;">Mgmt Fee</th>
                            <th style="padding: 0.5rem 0.75rem; text-align: right;">Advance / Maint. Offset</th>
                            <th style="padding: 0.5rem 0.75rem; text-align: right;">Net Payout</th>
                            <th style="padding: 0.5rem 0.75rem; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            <tr style="border-bottom: 1px solid #f1f5f9; {{ $item['status'] === 'ready' ? 'background: #ffffff;' : 'background: #fafafa; opacity: 0.85;' }}">
                                <td style="padding: 0.625rem 0.75rem;">
                                    <div style="font-weight: 600; color: #0f172a;">{{ $item['property_name'] }}</div>
                                    <div style="color: #64748b; font-size: 0.6875rem;">Owner: {{ $item['owner_name'] }}</div>
                                    <div style="color: #94a3b8; font-size: 0.625rem;">{{ $item['bank_details_formatted'] }}</div>
                                </td>

                                <td style="padding: 0.625rem 0.75rem;">
                                    <div style="font-weight: 600; color: #1e3a8a;">
                                        {{ $item['formatted_period'] }}
                                    </div>
                                    @if($item['is_prorated'])
                                        <div style="margin-top: 0.125rem;">
                                            <span style="display: inline-flex; align-items: center; background: #fef3c7; color: #92400e; font-size: 0.625rem; font-weight: 700; padding: 0.1rem 0.375rem; border-radius: 9999px; border: 1px solid #fde68a;">
                                                ★ Prorated: {{ $item['days_active'] }} / {{ $item['total_days_in_month'] }} days
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                <td style="padding: 0.625rem 0.75rem; text-align: right; color: #0f172a; font-weight: 600;">
                                    ₹{{ number_format($item['gross_rent'], 2) }}
                                </td>

                                <td style="padding: 0.625rem 0.75rem; text-align: right; color: #dc2626;">
                                    -₹{{ number_format($item['management_fee'], 2) }}
                                    <div style="font-size: 0.625rem; color: #94a3b8;">({{ $item['management_fee_percent'] }}%)</div>
                                </td>

                                <td style="padding: 0.625rem 0.75rem; text-align: right; color: #d97706;">
                                    @if($item['advance_offset'] > 0)
                                        <div style="font-weight: 600;">-₹{{ number_format($item['advance_offset'], 2) }}</div>
                                        @if(!empty($item['maintenance_invoices']))
                                            <div style="display: flex; flex-direction: column; gap: 0.125rem; margin-top: 0.25rem; align-items: flex-end;">
                                                @foreach($item['maintenance_invoices'] as $mInv)
                                                    <span style="font-size: 0.625rem; background: #fef3c7; color: #92400e; padding: 0.05rem 0.375rem; border-radius: 0.25rem; border: 1px solid #fde68a;">
                                                        🔧 {{ $mInv['ticket_number'] }}: ₹{{ number_format($mInv['amount'], 2) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </td>

                                <td style="padding: 0.625rem 0.75rem; text-align: right;">
                                    <div style="font-weight: 700; font-size: 0.8125rem; color: #15803d;">
                                        ₹{{ number_format($item['net_payout'], 2) }}
                                    </div>
                                </td>

                                <td style="padding: 0.625rem 0.75rem; text-align: center;">
                                    @if($item['status'] === 'ready')
                                        <span style="display: inline-block; background: #dcfce7; color: #166534; font-size: 0.6875rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; border: 1px solid #bbf7d0;">
                                            Ready to Disburse
                                        </span>
                                    @elseif($item['status'] === 'already_processed')
                                        <span style="display: inline-block; background: #e0f2fe; color: #075985; font-size: 0.6875rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 9999px; border: 1px solid #bae6fd;">
                                            {{ $item['status_label'] }}
                                        </span>
                                    @else
                                        <span style="display: inline-block; background: #f1f5f9; color: #64748b; font-size: 0.6875rem; font-weight: 500; padding: 0.2rem 0.5rem; border-radius: 9999px;">
                                            {{ $item['status_label'] }}
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Confirmation Notice -->
    @if($summary['ready_count'] > 0)
        <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.75rem; color: #1e40af; display: flex; align-items: flex-start; gap: 0.5rem;">
            <span style="font-size: 1rem;">ℹ️</span>
            <div>
                Click <strong>"Proceed to Final Confirmation"</strong> below to review the disbursement summary and confirm General Ledger posting. Unpaid maintenance invoices listed will be automatically marked as <strong>Paid</strong>.
            </div>
        </div>
    @else
        <div style="background: #fefce8; border-left: 4px solid #eab308; padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.75rem; color: #854d0e; display: flex; align-items: flex-start; gap: 0.5rem;">
            <span style="font-size: 1rem;">⚠️</span>
            <div>
                All active properties have already had owner payouts processed or are not yet eligible for <strong>{{ $preview['month_name'] }}</strong>.
            </div>
        </div>
    @endif
</div>
