@php
    $statePath = $getStatePath();
    $selectedAgreements = $getState() ?? [];
    if (!is_array($selectedAgreements)) {
        $selectedAgreements = (array) $selectedAgreements;
    }
    $selectedIds = array_map('strval', $selectedAgreements);

    $month = isset($get) ? (int) ($get('month') ?: date('n')) : (int) date('n');
    $year = isset($get) ? (int) ($get('year') ?: date('Y')) : (int) date('Y');
    $propertyId = isset($get) && $get('property_id') ? (string) $get('property_id') : null;

    $service = app(\App\Domain\Finance\Services\RentBillingService::class);
    $preview = $service->getBulkGenerationPreview($month, $year, $propertyId);
    $summary = $preview['summary'];
    $items = $preview['items'];

    $readyItems = array_filter($items, fn ($i) => $i['status'] === 'ready');
    $allReadyIds = array_values(array_map('strval', array_column($readyItems, 'agreement_id')));

    $selectedReadyItems = array_filter($readyItems, fn ($i) => in_array((string) $i['agreement_id'], $selectedIds, true));
    $selectedReadyCount = count($selectedReadyItems);
    $selectedReadyAmount = array_sum(array_column($selectedReadyItems, 'total_amount'));

    $allReadySelected = count($allReadyIds) > 0 && count(array_intersect($allReadyIds, $selectedIds)) === count($allReadyIds);
@endphp

<div x-data="{ search: '' }" style="display: flex; flex-direction: column; gap: 1rem; width: 100%;">
    <!-- Top Summary Banner -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem;">
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem 1rem;">
            <div style="font-size: 0.6875rem; color: #64748b; font-weight: 600; text-transform: uppercase;">Billing Month</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">{{ $preview['month_name'] }}</div>
        </div>

        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.5rem; padding: 0.75rem 1rem;">
            <div style="font-size: 0.6875rem; color: #166534; font-weight: 600; text-transform: uppercase;">Selected to Generate</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #15803d; margin-top: 0.25rem;">
                {{ $selectedReadyCount }} <span style="font-size: 0.75rem; font-weight: 500; color: #4ade80;">/ {{ $summary['ready_count'] }} ready</span>
            </div>
        </div>

        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.5rem; padding: 0.75rem 1rem;">
            <div style="font-size: 0.6875rem; color: #1e40af; font-weight: 600; text-transform: uppercase;">Selected Total Value</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #1d4ed8; margin-top: 0.25rem;">₹{{ number_format($selectedReadyAmount, 2) }}</div>
        </div>

        @if($summary['already_generated_count'] > 0)
        <div style="background: #fefce8; border: 1px solid #fef08a; border-radius: 0.5rem; padding: 0.75rem 1rem;">
            <div style="font-size: 0.6875rem; color: #854d0e; font-weight: 600; text-transform: uppercase;">Already Invoiced</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #a16207; margin-top: 0.25rem;">{{ $summary['already_generated_count'] }} (Skipped)</div>
        </div>
        @endif
    </div>

    <!-- Search & Quick Selection Toolbar -->
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.625rem 0.875rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 220px;">
            <span style="color: #94a3b8; font-size: 0.875rem;">🔍</span>
            <input 
                type="text" 
                x-model="search" 
                placeholder="Filter by tenant, property, or agreement code..." 
                style="width: 100%; border: none; outline: none; font-size: 0.8125rem; color: #1e293b; background: transparent;"
            />
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
            @if(count($allReadyIds) > 0)
                <button 
                    type="button" 
                    @click="$wire.set('{{ $statePath }}', {{ json_encode($allReadyIds) }})"
                    style="font-size: 0.6875rem; font-weight: 600; color: #d97706; background: #fffbeb; border: 1px solid #fde68a; padding: 0.25rem 0.625rem; border-radius: 0.375rem; cursor: pointer; transition: all 0.15s ease;">
                    Select All Ready ({{ count($allReadyIds) }})
                </button>

                <button 
                    type="button" 
                    @click="$wire.set('{{ $statePath }}', [])"
                    style="font-size: 0.6875rem; font-weight: 600; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.625rem; border-radius: 0.375rem; cursor: pointer; transition: all 0.15s ease;">
                    Deselect All
                </button>
            @endif

            <span style="font-size: 0.75rem; color: #64748b; font-weight: 500; margin-left: 0.25rem;">
                {{ $selectedReadyCount }} of {{ count($allReadyIds) }} selected
            </span>
        </div>
    </div>

    <!-- Unified Invoices Table with Selection Checkboxes -->
    <div style="border: 1px solid #e2e8f0; border-radius: 0.5rem; overflow: hidden; background: #ffffff; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);">
        @if(empty($items))
            <div style="padding: 2.5rem; text-align: center; color: #64748b; font-size: 0.875rem;">
                No active tenancy agreements found for this period/property selection.
            </div>
        @else
            <div style="max-height: 420px; overflow-y: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.75rem;">
                    <thead>
                        <tr style="background: #f8fafc; color: #475569; font-weight: 600; border-bottom: 1px solid #e2e8f0; position: sticky; top: 0; z-index: 1;">
                            <th style="padding: 0.625rem 0.75rem; width: 44px; text-align: center;">
                                <input 
                                    type="checkbox"
                                    {{ $allReadySelected ? 'checked' : '' }}
                                    {{ empty($allReadyIds) ? 'disabled' : '' }}
                                    @change="$event.target.checked ? $wire.set('{{ $statePath }}', {{ json_encode($allReadyIds) }}) : $wire.set('{{ $statePath }}', [])"
                                    style="cursor: {{ empty($allReadyIds) ? 'not-allowed' : 'pointer' }}; width: 1.05rem; height: 1.05rem; border-radius: 0.25rem; border: 1px solid #cbd5e1; accent-color: #f59e0b;"
                                    title="Toggle selection for all eligible tenancies"
                                />
                            </th>
                            <th style="padding: 0.625rem 0.75rem;">Tenant & Agreement</th>
                            <th style="padding: 0.625rem 0.75rem;">Property</th>
                            <th style="padding: 0.625rem 0.75rem;">Handover Date</th>
                            <th style="padding: 0.625rem 0.75rem;">Billing Period</th>
                            <th style="padding: 0.625rem 0.75rem; text-align: right;">Rent Demand</th>
                            <th style="padding: 0.625rem 0.75rem; text-align: center;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($items as $item)
                            @php
                                $isReady = $item['status'] === 'ready';
                                $isSelected = in_array((string) $item['agreement_id'], $selectedIds, true) && $isReady;
                                $searchTarget = strtolower($item['tenant_name'] . ' ' . $item['agreement_code'] . ' ' . $item['property_name'] . ' ' . ($item['property_code'] ?? ''));
                            @endphp
                            <tr 
                                x-show="!search || '{{ $searchTarget }}'.includes(search.toLowerCase())"
                                style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.15s ease; {{ $isSelected ? 'background: #ffffff;' : ($isReady ? 'background: #fcfcfc; opacity: 0.85;' : 'background: #f8fafc; opacity: 0.6;') }}"
                            >
                                <td style="padding: 0.625rem 0.75rem; text-align: center;">
                                    <input 
                                        type="checkbox"
                                        value="{{ (string) $item['agreement_id'] }}"
                                        wire:model.live="{{ $statePath }}"
                                        {{ !$isReady ? 'disabled' : '' }}
                                        style="cursor: {{ $isReady ? 'pointer' : 'not-allowed' }}; width: 1.05rem; height: 1.05rem; border-radius: 0.25rem; border: 1px solid #cbd5e1; accent-color: #f59e0b;"
                                    />
                                </td>

                                <td style="padding: 0.625rem 0.75rem;">
                                    <div style="font-weight: 600; color: #0f172a;">{{ $item['tenant_name'] }}</div>
                                    <div style="color: #64748b; font-size: 0.6875rem; font-family: monospace;">{{ $item['agreement_code'] }}</div>
                                </td>

                                <td style="padding: 0.625rem 0.75rem;">
                                    <div style="color: #334155; font-weight: 500;">{{ $item['property_name'] }}</div>
                                    @if(!empty($item['property_code']))
                                        <div style="color: #64748b; font-size: 0.6875rem; font-family: monospace;">{{ $item['property_code'] }}</div>
                                    @endif
                                </td>

                                <td style="padding: 0.625rem 0.75rem; color: #475569;">
                                    {{ $item['handover_date_formatted'] }}
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
                                    @elseif($item['is_first_month'])
                                        <div style="margin-top: 0.125rem;">
                                            <span style="display: inline-flex; align-items: center; background: #e0f2fe; color: #0369a1; font-size: 0.625rem; font-weight: 700; padding: 0.1rem 0.375rem; border-radius: 9999px;">
                                                1st Month (Full)
                                            </span>
                                        </div>
                                    @endif
                                </td>

                                <td style="padding: 0.625rem 0.75rem; text-align: right;">
                                    <div style="font-weight: 700; font-size: 0.8125rem; color: #0f172a;">
                                        ₹{{ number_format($item['total_amount'], 2) }}
                                    </div>
                                    @if($item['is_prorated'] && $item['standard_rent'] != $item['rent_amount'])
                                        <div style="font-size: 0.6875rem; color: #94a3b8; text-decoration: line-through;">
                                            ₹{{ number_format($item['standard_rent'], 2) }}
                                        </div>
                                    @endif
                                </td>

                                <td style="padding: 0.625rem 0.75rem; text-align: center;">
                                    @if($isSelected)
                                        <span style="display: inline-block; background: #dcfce7; color: #166534; font-size: 0.6875rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; border: 1px solid #bbf7d0;">
                                            ✓ Selected
                                        </span>
                                    @elseif($isReady)
                                        <span style="display: inline-block; background: #f1f5f9; color: #64748b; font-size: 0.6875rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 9999px;">
                                            Excluded
                                        </span>
                                    @elseif($item['status'] === 'already_generated')
                                        <span style="display: inline-block; background: #e0f2fe; color: #075985; font-size: 0.6875rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 9999px; border: 1px solid #bae6fd;">
                                            {{ $item['status_label'] }}
                                        </span>
                                    @else
                                        <span style="display: inline-block; background: #f1f5f9; color: #64748b; font-size: 0.6875rem; font-weight: 500; padding: 0.2rem 0.5rem; border-radius: 9999px;" title="{{ $item['status_label'] }}">
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
    @if($selectedReadyCount > 0)
        <div style="background: #eff6ff; border-left: 4px solid #3b82f6; padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.75rem; color: #1e40af; display: flex; align-items: flex-start; gap: 0.5rem;">
            <span style="font-size: 1rem;">ℹ️</span>
            <div>
                <strong>{{ $selectedReadyCount }} {{ Str::plural('tenancy', $selectedReadyCount) }}</strong> selected (₹{{ number_format($selectedReadyAmount, 2) }} total). Click <strong>"Confirm & Generate Invoices"</strong> below to review ledger posting.
            </div>
        </div>
    @elseif($summary['ready_count'] > 0)
        <div style="background: #fefce8; border-left: 4px solid #eab308; padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.75rem; color: #854d0e; display: flex; align-items: flex-start; gap: 0.5rem;">
            <span style="font-size: 1rem;">⚠️</span>
            <div>
                No tenancies currently selected. Please check at least one tenancy from the table above before proceeding.
            </div>
        </div>
    @else
        <div style="background: #fefce8; border-left: 4px solid #eab308; padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.75rem; color: #854d0e; display: flex; align-items: flex-start; gap: 0.5rem;">
            <span style="font-size: 1rem;">⚠️</span>
            <div>
                All active tenancies for this property/period have already been invoiced or are not yet commenced for <strong>{{ $preview['month_name'] }}</strong>.
            </div>
        </div>
    @endif
</div>
