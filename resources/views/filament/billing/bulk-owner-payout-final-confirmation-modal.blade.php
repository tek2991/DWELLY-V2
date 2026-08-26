@php
    $month = isset($get) ? (int) ($get('month') ?: date('n')) : (int) date('n');
    $year = isset($get) ? (int) ($get('year') ?: date('Y')) : (int) date('Y');
    $service = app(\App\Domain\Finance\Services\OwnerPayoutService::class);
    $preview = $service->getBulkPayoutPreview($month, $year);
    $summary = $preview['summary'];
    $readyItems = array_filter($preview['items'], fn ($i) => $i['status'] === 'ready');

    $allMaintInvoices = [];
    foreach ($readyItems as $item) {
        if (!empty($item['maintenance_invoices'])) {
            foreach ($item['maintenance_invoices'] as $m) {
                $allMaintInvoices[] = array_merge($m, ['property_name' => $item['property_name'], 'owner_name' => $item['owner_name']]);
            }
        }
    }
@endphp

<div style="display: flex; flex-direction: column; gap: 1.25rem; width: 100%;">
    @if($summary['ready_count'] > 0)
        <!-- High Priority Confirmation Banner -->
        <div style="background: #fffbeb; border: 2px solid #f59e0b; border-radius: 0.75rem; padding: 1.25rem; display: flex; gap: 1rem; align-items: flex-start;">
            <div style="font-size: 2rem; line-height: 1; flex-shrink: 0;">⚠️</div>
            <div style="flex-grow: 1;">
                <div style="font-size: 1rem; font-weight: 700; color: #92400e;">
                    Final Confirmation: You are about to disburse {{ $summary['ready_count'] }} owner {{ Str::plural('payout', $summary['ready_count']) }}
                </div>
                <div style="font-size: 0.8125rem; color: #78350f; margin-top: 0.375rem; line-height: 1.5;">
                    Please confirm that you want to disburse a total net payout of <strong>₹{{ number_format($summary['total_net_payout'], 2) }}</strong> to property owners for <strong>{{ $preview['month_name'] }}</strong>.
                    <br/>
                    This action will book <strong>₹{{ number_format($summary['total_management_fee'], 2) }}</strong> as Management Fee Revenue on P&L, recover <strong>₹{{ number_format($summary['total_advance_offset'], 2) }}</strong> in maintenance/advances, and post General Ledger disbursements.
                </div>
            </div>
        </div>

        <!-- Summary Metrics Box -->
        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 0.75rem;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem 1rem; text-align: center;">
                <div style="font-size: 0.6875rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Payout Month</div>
                <div style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">{{ $preview['month_name'] }}</div>
            </div>

            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.5rem; padding: 0.75rem 1rem; text-align: center;">
                <div style="font-size: 0.6875rem; color: #166534; font-weight: 600; text-transform: uppercase;">Properties Ready</div>
                <div style="font-size: 1.125rem; font-weight: 700; color: #15803d; margin-top: 0.25rem;">{{ $summary['ready_count'] }}</div>
            </div>

            <div style="background: #faf5ff; border: 1px solid #e9d5ff; border-radius: 0.5rem; padding: 0.75rem 1rem; text-align: center;">
                <div style="font-size: 0.6875rem; color: #6b21a8; font-weight: 600; text-transform: uppercase;">Commission Revenue</div>
                <div style="font-size: 1.125rem; font-weight: 700; color: #7e22ce; margin-top: 0.25rem;">₹{{ number_format($summary['total_management_fee'], 2) }}</div>
            </div>

            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.5rem; padding: 0.75rem 1rem; text-align: center;">
                <div style="font-size: 0.6875rem; color: #1e40af; font-weight: 600; text-transform: uppercase;">Total Net Disbursed</div>
                <div style="font-size: 1.125rem; font-weight: 700; color: #1d4ed8; margin-top: 0.25rem;">₹{{ number_format($summary['total_net_payout'], 2) }}</div>
            </div>
        </div>

        <!-- Maintenance Invoices Settlement Box -->
        @if(!empty($allMaintInvoices))
            <div style="border: 1px solid #fde68a; background: #fffdf5; border-radius: 0.5rem; overflow: hidden;">
                <div style="padding: 0.625rem 0.875rem; background: #fef3c7; border-bottom: 1px solid #fde68a; font-size: 0.75rem; font-weight: 700; color: #92400e; display: flex; justify-content: space-between; align-items: center;">
                    <span>🔧 Maintenance Invoices Automatically Settled as "Paid" ({{ count($allMaintInvoices) }})</span>
                    <span>Total Offset: ₹{{ number_format($summary['total_maintenance_offset'] ?? 0, 2) }}</span>
                </div>
                <div style="padding: 0.5rem 0.75rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    @foreach($allMaintInvoices as $mInv)
                        <div style="background: #ffffff; border: 1px solid #fde68a; border-radius: 0.375rem; padding: 0.375rem 0.625rem; font-size: 0.6875rem; display: flex; gap: 0.5rem; align-items: center;">
                            <span style="font-weight: 700; color: #b45309;">{{ $mInv['ticket_number'] }}</span>
                            <span style="color: #64748b;">({{ $mInv['property_name'] }})</span>
                            <span style="font-weight: 700; color: #0f172a;">₹{{ number_format($mInv['amount'], 2) }}</span>
                            <span style="color: #15803d; font-weight: 600;">➔ Paid</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- List of Owner Payouts being processed -->
        <div style="border: 1px solid #e2e8f0; border-radius: 0.5rem; overflow: hidden; background: #ffffff;">
            <div style="padding: 0.625rem 0.875rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 0.75rem; font-weight: 700; color: #334155;">
                Disbursement List ({{ count($readyItems) }})
            </div>
            <div style="max-height: 240px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.75rem;">
                    <thead>
                        <tr style="background: #f1f5f9; color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 0.5rem 0.75rem;">Owner & Property</th>
                            <th style="padding: 0.5rem 0.75rem;">Bank Account</th>
                            <th style="padding: 0.5rem 0.75rem;">Billing Period</th>
                            <th style="padding: 0.5rem 0.75rem; text-align: right;">Gross Rent</th>
                            <th style="padding: 0.5rem 0.75rem; text-align: right;">Fee (10%)</th>
                            <th style="padding: 0.5rem 0.75rem; text-align: right;">Net Payout</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($readyItems as $item)
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.5rem 0.75rem;">
                                    <div style="font-weight: 600; color: #0f172a;">{{ $item['owner_name'] }}</div>
                                    <div style="color: #64748b; font-size: 0.6875rem;">{{ $item['property_name'] }}</div>
                                </td>
                                <td style="padding: 0.5rem 0.75rem; color: #475569; font-size: 0.6875rem;">
                                    {{ $item['bank_details_formatted'] }}
                                </td>
                                <td style="padding: 0.5rem 0.75rem; color: #1e3a8a; font-weight: 500;">
                                    {{ $item['formatted_period'] }}
                                    @if($item['is_prorated'])
                                        <span style="font-size: 0.625rem; color: #d97706; font-weight: 700;">(Prorated)</span>
                                    @endif
                                </td>
                                <td style="padding: 0.5rem 0.75rem; text-align: right; color: #0f172a;">
                                    ₹{{ number_format($item['gross_rent'], 2) }}
                                </td>
                                <td style="padding: 0.5rem 0.75rem; text-align: right; color: #dc2626;">
                                    -₹{{ number_format($item['management_fee'], 2) }}
                                </td>
                                <td style="padding: 0.5rem 0.75rem; text-align: right; font-weight: 700; color: #15803d;">
                                    ₹{{ number_format($item['net_payout'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <!-- No Payouts Banner -->
        <div style="background: #fefce8; border: 1px solid #fef08a; border-radius: 0.75rem; padding: 1.25rem; text-align: center;">
            <div style="font-size: 1.5rem;">⚠️</div>
            <div style="font-size: 0.875rem; font-weight: 700; color: #854d0e; margin-top: 0.5rem;">
                No pending owner payouts to disburse
            </div>
            <div style="font-size: 0.75rem; color: #a16207; margin-top: 0.25rem;">
                All active properties have already had owner payouts processed or are not yet eligible for {{ $preview['month_name'] }}.
            </div>
        </div>
    @endif
</div>
