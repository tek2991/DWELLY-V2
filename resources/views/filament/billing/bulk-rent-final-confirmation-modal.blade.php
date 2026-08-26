@php
    $month = isset($get) ? (int) ($get('month') ?: date('n')) : (int) date('n');
    $year = isset($get) ? (int) ($get('year') ?: date('Y')) : (int) date('Y');
    $propertyId = isset($get) && $get('property_id') ? (int) $get('property_id') : null;
    $selectedAgreements = isset($get) ? (array) ($get('selected_agreements') ?? []) : [];
    $selectedIds = array_map('strval', $selectedAgreements);

    $service = app(\App\Domain\Finance\Services\RentBillingService::class);
    $preview = $service->getBulkGenerationPreview($month, $year, $propertyId);
    $summary = $preview['summary'];
    
    // Filter to only the selected ready items
    $readyItems = array_filter($preview['items'], fn ($i) => in_array((string) $i['agreement_id'], $selectedIds, true) && $i['status'] === 'ready');
    $selectedCount = count($readyItems);
    $selectedTotalAmount = array_sum(array_column($readyItems, 'total_amount'));
@endphp

<div style="display: flex; flex-direction: column; gap: 1.25rem; width: 100%;">
    @if($selectedCount > 0)
        <!-- High Priority Confirmation Banner -->
        <div style="background: #fffbeb; border: 2px solid #f59e0b; border-radius: 0.75rem; padding: 1.25rem; display: flex; gap: 1rem; align-items: flex-start;">
            <div style="font-size: 2rem; line-height: 1; flex-shrink: 0;">⚠️</div>
            <div style="flex-grow: 1;">
                <div style="font-size: 1rem; font-weight: 700; color: #92400e;">
                    Final Confirmation: You are about to generate {{ $selectedCount }} rent {{ Str::plural('invoice', $selectedCount) }}
                </div>
                <div style="font-size: 0.8125rem; color: #78350f; margin-top: 0.375rem; line-height: 1.5;">
                    Please confirm that you want to generate monthly rent demands for <strong>{{ $preview['month_name'] }}</strong> for a total amount of <strong>₹{{ number_format($selectedTotalAmount, 2) }}</strong> across <strong>{{ $selectedCount }}</strong> selected {{ Str::plural('tenancy', $selectedCount) }}.
                    <br/>
                    This action will immediately post double-entry transactions (<strong>DR Tenant Receivable</strong>, <strong>CR Owner Payable</strong>) to the General Ledger.
                </div>
            </div>
        </div>

        <!-- Summary Metrics Box -->
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.75rem;">
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem 1rem; text-align: center;">
                <div style="font-size: 0.6875rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Billing Month</div>
                <div style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">{{ $preview['month_name'] }}</div>
            </div>

            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.5rem; padding: 0.75rem 1rem; text-align: center;">
                <div style="font-size: 0.6875rem; color: #166534; font-weight: 600; text-transform: uppercase;">Invoices to Generate</div>
                <div style="font-size: 1.125rem; font-weight: 700; color: #15803d; margin-top: 0.25rem;">{{ $selectedCount }}</div>
            </div>

            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.5rem; padding: 0.75rem 1rem; text-align: center;">
                <div style="font-size: 0.6875rem; color: #1e40af; font-weight: 600; text-transform: uppercase;">Total Billing Amount</div>
                <div style="font-size: 1.125rem; font-weight: 700; color: #1d4ed8; margin-top: 0.25rem;">₹{{ number_format($selectedTotalAmount, 2) }}</div>
            </div>
        </div>

        <!-- List of Invoices being generated -->
        <div style="border: 1px solid #e2e8f0; border-radius: 0.5rem; overflow: hidden; background: #ffffff;">
            <div style="padding: 0.625rem 0.875rem; background: #f8fafc; border-bottom: 1px solid #e2e8f0; font-size: 0.75rem; font-weight: 700; color: #334155;">
                Selected Invoices Summary ({{ $selectedCount }})
            </div>
            <div style="max-height: 240px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.75rem;">
                    <thead>
                        <tr style="background: #f1f5f9; color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0;">
                            <th style="padding: 0.5rem 0.75rem;">Tenant / Agreement</th>
                            <th style="padding: 0.5rem 0.75rem;">Property</th>
                            <th style="padding: 0.5rem 0.75rem;">Billing Period</th>
                            <th style="padding: 0.5rem 0.75rem; text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($readyItems as $item)
                            <tr style="border-bottom: 1px solid #f1f5f9;">
                                <td style="padding: 0.5rem 0.75rem;">
                                    <span style="font-weight: 600; color: #0f172a;">{{ $item['tenant_name'] }}</span>
                                    <span style="color: #64748b; font-size: 0.6875rem; margin-left: 0.25rem;">({{ $item['agreement_code'] }})</span>
                                </td>
                                <td style="padding: 0.5rem 0.75rem;">
                                    <div style="color: #334155; font-weight: 500;">{{ $item['property_name'] }}</div>
                                    @if(!empty($item['property_code']))
                                        <div style="color: #64748b; font-size: 0.6875rem; font-family: monospace;">{{ $item['property_code'] }}</div>
                                    @endif
                                </td>
                                <td style="padding: 0.5rem 0.75rem; color: #1e3a8a; font-weight: 500;">
                                    {{ $item['formatted_period'] }}
                                    @if($item['is_prorated'])
                                        <span style="font-size: 0.625rem; color: #d97706; font-weight: 700;">(Prorated)</span>
                                    @endif
                                </td>
                                <td style="padding: 0.5rem 0.75rem; text-align: right; font-weight: 700; color: #0f172a;">
                                    ₹{{ number_format($item['total_amount'], 2) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @else
        <!-- No Invoices Selected Banner -->
        <div style="background: #fefce8; border: 1px solid #fef08a; border-radius: 0.75rem; padding: 1.25rem; text-align: center;">
            <div style="font-size: 1.5rem;">⚠️</div>
            <div style="font-size: 0.875rem; font-weight: 700; color: #854d0e; margin-top: 0.5rem;">
                No tenancies selected for generation
            </div>
            <div style="font-size: 0.75rem; color: #a16207; margin-top: 0.25rem;">
                Please navigate back to Step 1 and select at least one active tenancy agreement to invoice.
            </div>
        </div>
    @endif
</div>
