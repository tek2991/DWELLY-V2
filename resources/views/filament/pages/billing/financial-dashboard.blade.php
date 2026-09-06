<x-filament-panels::page>
    @php
        $tabCounts = $this->getTabCounts();
    @endphp

    <div style="display: flex; flex-direction: column; gap: 1.25rem; width: 100%;">
        
        <!-- Tab Navigation Bar -->
        <div style="background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.3125rem; display: flex; gap: 0.3125rem; overflow-x: auto;">
            
            <!-- Tab 1: Executive Overview -->
            <button 
                type="button"
                wire:click="setTab('overview')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'overview' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <x-filament::icon icon="heroicon-m-chart-pie" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'overview' ? '#4f46e5' : '#94a3b8' }};" />
                <span>Executive P&L & Cashflow</span>
            </button>

            <!-- Tab 2: AR Aging & Collections -->
            <button 
                type="button"
                wire:click="setTab('rent_aging')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'rent_aging' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <x-filament::icon icon="heroicon-m-clock" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'rent_aging' ? '#dc2626' : '#94a3b8' }};" />
                <span>Rent Collections & AR Aging</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'rent_aging' ? 'background: #fee2e2; color: #dc2626;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $tabCounts['rent_aging'] }}
                </span>
            </button>

            <!-- Tab 3: Owner Disbursals & AP -->
            <button 
                type="button"
                wire:click="setTab('payouts')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'payouts' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <x-filament::icon icon="heroicon-m-arrow-up-tray" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'payouts' ? '#2563eb' : '#94a3b8' }};" />
                <span>Owner Disbursals & AP</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'payouts' ? 'background: #dbeafe; color: #1d4ed8;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $tabCounts['payouts'] }}
                </span>
            </button>

            <!-- Tab 4: Security Deposits -->
            <button 
                type="button"
                wire:click="setTab('deposits')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'deposits' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <x-filament::icon icon="heroicon-m-shield-check" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'deposits' ? '#059669' : '#94a3b8' }};" />
                <span>Deposits & Escrow Float</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'deposits' ? 'background: #ecfdf5; color: #059669;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $tabCounts['deposits'] }}
                </span>
            </button>

            <!-- Tab 5: Maintenance Recovery -->
            <button 
                type="button"
                wire:click="setTab('maintenance')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'maintenance' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <x-filament::icon icon="heroicon-m-wrench-screwdriver" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'maintenance' ? '#d97706' : '#94a3b8' }};" />
                <span>Maintenance Billing</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'maintenance' ? 'background: #fffbeb; color: #d97706;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $tabCounts['maintenance'] }}
                </span>
            </button>

        </div>

        <!-- ================= TAB 1: EXECUTIVE OVERVIEW ================= -->
        @if ($activeTab === 'overview')
            @php
                $overview = $this->getOverviewData();
            @endphp

            <!-- KPI Cards Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.875rem;">
                
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Gross Billed (This Month)</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">₹{{ number_format($overview['gross_billed'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Rent & service invoices</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Collections & Rate</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #059669; margin-top: 0.25rem;">₹{{ number_format($overview['rent_collected'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #059669; font-weight: 700; margin-top: 0.25rem;">{{ $overview['collection_rate'] }}% collection efficiency</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Owner Disbursals Paid</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #2563eb; margin-top: 0.25rem;">₹{{ number_format($overview['disbursed_total'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Disbursed to property owners</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Dwelly Fees Earned</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #7e22ce; margin-top: 0.25rem;">₹{{ number_format($overview['commission_earned'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #7e22ce; font-weight: 700; margin-top: 0.25rem;">MOU Commission Revenue</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #ede9fe; border-radius: 0.75rem; padding: 1.125rem 1.25rem; border-left: 4px solid #8b5cf6;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #6d28d9; text-transform: uppercase;">Expiry Churn Exposure (&lt; 60d)</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #6d28d9; margin-top: 0.25rem;">₹{{ number_format($overview['rent_at_risk_60d'], 0) }}</div>
                    <div style="font-size: 0.75rem; color: #7c3aed; font-weight: 600; margin-top: 0.25rem;">Across {{ $overview['expiring_count_60d'] }} leases (monthly at risk)</div>
                </div>

            </div>

            <!-- Trailing Months Performance Table -->
            <x-filament::section>
                <x-slot name="heading">
                    <span style="font-weight: 700; font-size: 0.9375rem;">Trailing 6-Month Cash Flow & Revenue Performance</span>
                </x-slot>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                <th style="padding: 0.625rem 0.75rem;">Month</th>
                                <th style="padding: 0.625rem 0.75rem;">Collections Realized</th>
                                <th style="padding: 0.625rem 0.75rem;">Owner Disbursals</th>
                                <th style="padding: 0.625rem 0.75rem;">Commission Fee</th>
                                <th style="padding: 0.625rem 0.75rem; text-align: right;">Net Float Retained</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($overview['trailing_months'] as $tm)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.625rem 0.75rem; font-weight: 700; color: #0f172a;">{{ $tm['month'] }}</td>
                                    <td style="padding: 0.625rem 0.75rem; color: #059669; font-weight: 600;">₹{{ number_format($tm['revenue'], 2) }}</td>
                                    <td style="padding: 0.625rem 0.75rem; color: #dc2626; font-weight: 600;">₹{{ number_format($tm['payouts'], 2) }}</td>
                                    <td style="padding: 0.625rem 0.75rem; color: #7e22ce; font-weight: 700;">₹{{ number_format($tm['fees'], 2) }}</td>
                                    <td style="padding: 0.625rem 0.75rem; text-align: right; font-weight: 800; color: {{ $tm['net_margin'] >= 0 ? '#0f172a' : '#dc2626' }};">
                                        ₹{{ number_format($tm['net_margin'], 2) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

        <!-- ================= TAB 2: AR AGING & COLLECTIONS ================= -->
        @elseif ($activeTab === 'rent_aging')
            @php
                $ar = $this->getRentAgingData();
            @endphp

            <!-- Aging Buckets Strip -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.875rem;">
                
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem; border-top: 4px solid #059669;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Current (0–30 Days)</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">₹{{ number_format($ar['bucket_current'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #059669; margin-top: 0.25rem;">Regular billing cycle</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem; border-top: 4px solid #f59e0b;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">31–60 Days Overdue</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #d97706; margin-top: 0.25rem;">₹{{ number_format($ar['bucket_31_60'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #d97706; margin-top: 0.25rem;">Reminder notice sent</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem; border-top: 4px solid #ea580c;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">61–90 Days Overdue</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #c2410c; margin-top: 0.25rem;">₹{{ number_format($ar['bucket_61_90'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #ea580c; margin-top: 0.25rem;">Final legal warning</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem; border-top: 4px solid #dc2626;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">90+ Days Critical</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #991b1b; margin-top: 0.25rem;">₹{{ number_format($ar['bucket_90_plus'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #dc2626; margin-top: 0.25rem;">Eviction / settlement required</div>
                </div>

            </div>

            <!-- Delinquent Invoices Table -->
            <x-filament::section>
                <x-slot name="heading">
                    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        <span style="font-weight: 700; font-size: 0.9375rem;">Outstanding Receivables & Tenant Delinquency</span>
                        <a href="{{ url('/operations/bulk-generate-monthly-rent') }}" style="font-size: 0.75rem; color: #0284c7; text-decoration: none; font-weight: 600;">Monthly Billing Hub &rarr;</a>
                    </div>
                </x-slot>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                <th style="padding: 0.625rem 0.5rem;">Invoice #</th>
                                <th style="padding: 0.625rem 0.5rem;">Tenant / Contact</th>
                                <th style="padding: 0.625rem 0.5rem;">Due Date</th>
                                <th style="padding: 0.625rem 0.5rem;">Balance Due</th>
                                <th style="padding: 0.625rem 0.5rem;">Status</th>
                                <th style="padding: 0.625rem 0.5rem; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($ar['delinquent_invoices'] as $inv)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #0f172a;">{{ $inv->invoice_number }}</td>
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 600; color: #1e293b;">
                                        {{ $inv->contact?->name ?? 'Tenant' }}
                                    </td>
                                    <td style="padding: 0.625rem 0.5rem; color: #475569;">
                                        {{ $inv->due_date ? \Carbon\Carbon::parse($inv->due_date)->format('d M Y') : 'N/A' }}
                                    </td>
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 800; color: #dc2626;">
                                        ₹{{ number_format($inv->balance_due, 2) }}
                                    </td>
                                    <td style="padding: 0.625rem 0.5rem;">
                                        <span style="font-size: 0.6875rem; font-weight: 600; color: #d97706; background: #fffbeb; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                                            {{ $inv->status->getLabel() }}
                                        </span>
                                    </td>
                                    <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                        <a href="{{ url('/operations/financial-operations-hub') }}" style="font-weight: 600; color: #0284c7; text-decoration: none;">Collect</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding: 1.5rem; text-align: center; color: #64748b;">No outstanding rent demands found. All accounts are paid up!</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

        <!-- ================= TAB 3: OWNER PAYOUTS & AP ================= -->
        @elseif ($activeTab === 'payouts')
            @php
                $payouts = $this->getPayoutsData();
            @endphp

            <!-- Waterfall Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.875rem;">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Gross Rent Collected</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">₹{{ number_format($payouts['gross_rent'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Base payout pool</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Management Fees Withheld</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #7e22ce; margin-top: 0.25rem;">₹{{ number_format($payouts['management_fees'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #7e22ce; margin-top: 0.25rem;">Dwelly commission retained</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Net Disbursed to Owners</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #059669; margin-top: 0.25rem;">₹{{ number_format($payouts['net_disbursed'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #059669; margin-top: 0.25rem;">Disbursed successfully</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #dbeafe; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #1e40af; text-transform: uppercase;">Pending Disbursals</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #2563eb; margin-top: 0.25rem;">₹{{ number_format($payouts['pending_amount'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #2563eb; font-weight: 600; margin-top: 0.25rem;">{{ $payouts['pending_count'] }} payouts awaiting batch execution</div>
                </div>
            </div>

            <!-- Recent Payout Disbursals Table -->
            <x-filament::section>
                <x-slot name="heading">
                    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        <span style="font-weight: 700; font-size: 0.9375rem;">Owner Payout History & Batch Logs</span>
                        <a href="{{ url('/operations/bulk-generate-owner-payouts') }}" style="font-size: 0.75rem; color: #2563eb; text-decoration: none; font-weight: 600;">Bulk Disbursal Console &rarr;</a>
                    </div>
                </x-slot>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                <th style="padding: 0.625rem 0.5rem;">Property</th>
                                <th style="padding: 0.625rem 0.5rem;">Owner</th>
                                <th style="padding: 0.625rem 0.5rem;">Period</th>
                                <th style="padding: 0.625rem 0.5rem;">Net Amount</th>
                                <th style="padding: 0.625rem 0.5rem;">MOU Fee</th>
                                <th style="padding: 0.625rem 0.5rem;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($payouts['recent_payouts'] as $payout)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #0f172a;">{{ $payout->property?->building_name ?? 'Property' }}</td>
                                    <td style="padding: 0.625rem 0.5rem; color: #475569;">{{ $payout->owner?->display_name ?? 'Owner' }}</td>
                                    <td style="padding: 0.625rem 0.5rem; color: #64748b;">{{ $payout->period_formatted ?? 'N/A' }}</td>
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 800; color: #0f172a;">₹{{ number_format($payout->amount, 2) }}</td>
                                    <td style="padding: 0.625rem 0.5rem; color: #7e22ce; font-weight: 600;">₹{{ number_format($payout->management_fee, 2) }}</td>
                                    <td style="padding: 0.625rem 0.5rem;">
                                        <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.125rem 0.5rem; border-radius: 9999px; {{ $payout->status === 'completed' ? 'background: #ecfdf5; color: #059669;' : 'background: #eff6ff; color: #2563eb;' }}">
                                            {{ strtoupper($payout->status) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding: 1.5rem; text-align: center; color: #64748b;">No owner payouts recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

        <!-- ================= TAB 4: DEPOSITS & ESCROW ================= -->
        @elseif ($activeTab === 'deposits')
            @php
                $dep = $this->getDepositsData();
            @endphp

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.875rem;">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Deposits Held in Trust</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #059669; margin-top: 0.25rem;">₹{{ number_format($dep['active_deposits_total'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Across {{ $dep['active_deposits_count'] }} active tenant agreements</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Pending Move-Out Settlement</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #d97706; margin-top: 0.25rem;">₹{{ number_format($dep['pending_settlements'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #d97706; margin-top: 0.25rem;">Under inspection / audit deductions</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Deductions Withheld YTD</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #dc2626; margin-top: 0.25rem;">₹{{ number_format($dep['deductions_ytd'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Damages & unpaid utilities offset</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Net Refunded YTD</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">₹{{ number_format($dep['refunded_ytd'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Returned to vacated tenants</div>
                </div>
            </div>

            <!-- Active Deposits Table -->
            <x-filament::section>
                <x-slot name="heading">
                    <span style="font-weight: 700; font-size: 0.9375rem;">Active Tenant Security Deposits Register</span>
                </x-slot>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                <th style="padding: 0.625rem 0.5rem;">Agreement #</th>
                                <th style="padding: 0.625rem 0.5rem;">Property</th>
                                <th style="padding: 0.625rem 0.5rem;">Security Deposit Held</th>
                                <th style="padding: 0.625rem 0.5rem;">Monthly Rent</th>
                                <th style="padding: 0.625rem 0.5rem; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($dep['agreements'] as $ag)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #0f172a;">{{ $ag->code }}</td>
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 600; color: #1e293b;">{{ $ag->property?->building_name ?? 'Property' }}</td>
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 800; color: #059669;">₹{{ number_format($ag->security_deposit, 2) }}</td>
                                    <td style="padding: 0.625rem 0.5rem; color: #475569;">₹{{ number_format($ag->rent_amount, 2) }}</td>
                                    <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                        <a href="{{ url('/operations/financial-operations-hub') }}" style="font-weight: 600; color: #0284c7; text-decoration: none;">Manage</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" style="padding: 1.5rem; text-align: center; color: #64748b;">No active security deposits recorded.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

        <!-- ================= TAB 5: MAINTENANCE BILLING ================= -->
        @elseif ($activeTab === 'maintenance')
            @php
                $mf = $this->getMaintenanceFinancialData();
            @endphp

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.875rem;">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Total Invoiced</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">₹{{ number_format($mf['total_invoiced'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Repairs billed to owners/tenants</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Recovered & Paid</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #059669; margin-top: 0.25rem;">₹{{ number_format($mf['total_collected'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #059669; margin-top: 0.25rem;">Successfully settled</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.125rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Pending Settlement</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #d97706; margin-top: 0.25rem;">₹{{ number_format($mf['total_pending'], 2) }}</div>
                    <div style="font-size: 0.75rem; color: #d97706; margin-top: 0.25rem;">To offset against payout/rent</div>
                </div>
            </div>

            <!-- Maintenance Invoices Table -->
            <x-filament::section>
                <x-slot name="heading">
                    <span style="font-weight: 700; font-size: 0.9375rem;">Maintenance Invoices & Work Order Cost Settlements</span>
                </x-slot>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                <th style="padding: 0.625rem 0.5rem;">Invoice #</th>
                                <th style="padding: 0.625rem 0.5rem;">Amount</th>
                                <th style="padding: 0.625rem 0.5rem;">Paid</th>
                                <th style="padding: 0.625rem 0.5rem;">Balance</th>
                                <th style="padding: 0.625rem 0.5rem;">Status</th>
                                <th style="padding: 0.625rem 0.5rem; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($mf['invoices'] as $minv)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #0f172a;">{{ $minv->invoice_number }}</td>
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 600; color: #0f172a;">₹{{ number_format($minv->grand_total, 2) }}</td>
                                    <td style="padding: 0.625rem 0.5rem; color: #059669;">₹{{ number_format($minv->amount_paid, 2) }}</td>
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: {{ $minv->balance_due > 0 ? '#dc2626' : '#64748b' }};">
                                        ₹{{ number_format($minv->balance_due, 2) }}
                                    </td>
                                    <td style="padding: 0.625rem 0.5rem;">
                                        <span style="font-size: 0.6875rem; font-weight: 600; color: #d97706; background: #fffbeb; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                                            {{ $minv->status->getLabel() }}
                                        </span>
                                    </td>
                                    <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                        <a href="{{ url('/operations/financial-operations-hub') }}" style="font-weight: 600; color: #0284c7; text-decoration: none;">Manage</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding: 1.5rem; text-align: center; color: #64748b;">No maintenance invoices recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

        @endif

    </div>
</x-filament-panels::page>
