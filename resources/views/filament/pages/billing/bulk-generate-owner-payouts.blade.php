<x-filament-panels::page>
    @php
        $preview = $this->getPreviewData();
        $summary = $preview['summary'];
        $filteredItems = $this->getFilteredItems();
        $paginatedItems = $this->getPaginatedItems();
        $selectedSummary = $this->getSelectedSummary();
        $bankAccounts = $this->getBankAccountsProperty();
        $monthName = $preview['month_name'];
        $selectedIds = array_map('strval', $this->selectedProperties);

        $months = [
            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
        ];
    @endphp

    <div x-data="{
        confirmModalOpen: false,
        activeRowDetails: null,
    }" style="display: flex; flex-direction: column; gap: 1.5rem; width: 100%;">

        <!-- Post-Execution Summary Banner (If just executed) -->
        @if($this->lastExecutionSummary)
            <div style="background: #f0fdf4; border: 1px solid #86efac; border-radius: 0.75rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 2.5rem; height: 2.5rem; border-radius: 9999px; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1.5rem; height: 1.5rem;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                        <div>
                            <h3 style="font-size: 1rem; font-weight: 700; color: #166534; margin: 0;">
                                Successfully Disbursed {{ $this->lastExecutionSummary['count'] }} Owner Payouts
                            </h3>
                            <p style="font-size: 0.8125rem; color: #15803d; margin: 0.25rem 0 0 0;">
                                Total net cash disbursed: <strong>₹{{ number_format($this->lastExecutionSummary['total_amount'], 2) }}</strong> | Total Dwelly Fee Revenue: <strong>₹{{ number_format($this->lastExecutionSummary['total_management_fee'], 2) }}</strong> for <strong>{{ $monthName }}</strong>. Official Tax Invoices and immutable Payout Statements have been generated.
                            </p>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        wire:click="dismissExecutionSummary"
                        style="background: transparent; border: none; color: #15803d; cursor: pointer; font-size: 0.8125rem; font-weight: 600;">
                        ✕ Dismiss
                    </button>
                </div>

                @if(!empty($this->lastExecutionSummary['generated_payouts']))
                    <div style="margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        @foreach($this->lastExecutionSummary['generated_payouts'] as $gen)
                            <div style="display: inline-flex; align-items: center; gap: 0.5rem; background: #ffffff; border: 1px solid #bbf7d0; border-radius: 0.375rem; padding: 0.375rem 0.625rem; font-size: 0.75rem; font-weight: 600; color: #166534;">
                                <span>🏦 {{ $gen['property_name'] }} ({{ $gen['owner_name'] }})</span>
                                <span style="color: #0f172a; font-weight: 700;">₹{{ number_format($gen['net_amount'], 2) }}</span>
                                @if(!empty($gen['commission_invoice_number']))
                                    <span style="color: #2563eb; font-weight: 500; font-size: 0.6875rem;">(Tax Inv #{{ $gen['commission_invoice_number'] }})</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif

        <!-- Top Period & Settings Bar -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.25rem 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 1.25rem;">
            <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <!-- Period Selector Group -->
                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.25rem 0.5rem;">
                        <button 
                            type="button" 
                            wire:click="previousMonth"
                            title="Previous Month"
                            style="background: transparent; border: none; cursor: pointer; padding: 0.25rem 0.5rem; color: #475569; font-weight: bold; border-radius: 0.25rem; font-size: 0.875rem;">
                            ◀
                        </button>

                        <div style="display: flex; align-items: center; gap: 0.375rem; padding: 0 0.5rem;">
                            <select 
                                wire:model.live="month" 
                                style="border: none; background: transparent; font-size: 0.9375rem; font-weight: 700; color: #0f172a; cursor: pointer; outline: none;">
                                @foreach($months as $num => $name)
                                    <option value="{{ $num }}">{{ $name }}</option>
                                @endforeach
                            </select>

                            <input 
                                type="number" 
                                wire:model.live.debounce.500ms="year" 
                                min="2020" 
                                max="2050"
                                style="width: 4.5rem; border: none; background: transparent; font-size: 0.9375rem; font-weight: 700; color: #0f172a; text-align: center; outline: none;"
                            />
                        </div>

                        <button 
                            type="button" 
                            wire:click="nextMonth"
                            title="Next Month"
                            style="background: transparent; border: none; cursor: pointer; padding: 0.25rem 0.5rem; color: #475569; font-weight: bold; border-radius: 0.25rem; font-size: 0.875rem;">
                            ▶
                        </button>
                    </div>

                    <button 
                        type="button" 
                        wire:click="currentMonth"
                        style="background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 0.375rem; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600; color: #334155; cursor: pointer;">
                        Current Cycle
                    </button>
                </div>

                <!-- Disbursement Bank & Date Controls -->
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Disbursement Bank:</span>
                        <select 
                            wire:model.live="bankAccountId"
                            style="font-size: 0.8125rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.375rem 0.625rem; background: #ffffff; color: #0f172a; font-weight: 500;">
                            @foreach($bankAccounts as $bank)
                                <option value="{{ $bank->id }}">{{ $bank->name }} (Code: {{ $bank->code }})</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 0.75rem; font-weight: 600; color: #475569;">Payout Date:</span>
                        <input 
                            type="date" 
                            wire:model.live="payoutDate"
                            style="font-size: 0.8125rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.375rem 0.625rem; background: #ffffff; color: #0f172a; font-weight: 500;"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- 4 KPI Metric Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
            <!-- Ready Card -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-left: 4px solid #16a34a; border-radius: 0.75rem; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.025em;">
                    Ready to Disburse
                </div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #16a34a; margin-top: 0.25rem;">
                    ₹{{ number_format($summary['total_net_payout'], 2) }}
                </div>
                <div style="font-size: 0.75rem; color: #475569; margin-top: 0.25rem;">
                    <strong>{{ $summary['ready_count'] }}</strong> of {{ $summary['total_properties'] }} properties ready
                </div>
            </div>

            <!-- Gross Rental Inflows -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-left: 4px solid #2563eb; border-radius: 0.75rem; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.025em;">
                    Gross Rental Inflows
                </div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">
                    ₹{{ number_format($summary['total_gross_rent'], 2) }}
                </div>
                <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                    Active tenant rent collections
                </div>
            </div>

            <!-- Commission Revenue Card -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-left: 4px solid #d97706; border-radius: 0.75rem; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.025em;">
                    Dwelly Fee Revenue
                </div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #d97706; margin-top: 0.25rem;">
                    ₹{{ number_format($summary['total_management_fee'], 2) }}
                </div>
                <div style="font-size: 0.75rem; color: #92400e; margin-top: 0.25rem;">
                    ★ 10% Mgmt Tax Invoices auto-generated
                </div>
            </div>

            <!-- Maintenance & Advances Offset -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-left: 4px solid #dc2626; border-radius: 0.75rem; padding: 1rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div style="font-size: 0.75rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.025em;">
                    Maintenance / Advances Offset
                </div>
                <div style="font-size: 1.5rem; font-weight: 800; color: #dc2626; margin-top: 0.25rem;">
                    -₹{{ number_format($summary['total_advance_offset'], 2) }}
                </div>
                <div style="font-size: 0.75rem; color: #991b1b; margin-top: 0.25rem;">
                    🔧 Invoices marked Paid at source
                </div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.875rem 1.25rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
            <!-- Status Filter Tabs -->
            <div style="display: flex; gap: 0.375rem; background: #f1f5f9; padding: 0.25rem; border-radius: 0.5rem; flex-wrap: wrap;">
                <button 
                    type="button" 
                    wire:click="setStatusFilter('all')"
                    style="border: none; cursor: pointer; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600; border-radius: 0.375rem; transition: all 0.15s ease; {{ $this->statusFilter === 'all' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 2px rgba(0,0,0,0.05);' : 'background: transparent; color: #64748b;' }}">
                    All Properties ({{ $summary['total_properties'] }})
                </button>
                <button 
                    type="button" 
                    wire:click="setStatusFilter('ready')"
                    style="border: none; cursor: pointer; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600; border-radius: 0.375rem; transition: all 0.15s ease; {{ $this->statusFilter === 'ready' ? 'background: #ffffff; color: #16a34a; box-shadow: 0 1px 2px rgba(0,0,0,0.05);' : 'background: transparent; color: #64748b;' }}">
                    Ready ({{ $summary['ready_count'] }})
                </button>
                <button 
                    type="button" 
                    wire:click="setStatusFilter('already_processed')"
                    style="border: none; cursor: pointer; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600; border-radius: 0.375rem; transition: all 0.15s ease; {{ $this->statusFilter === 'already_processed' ? 'background: #ffffff; color: #2563eb; box-shadow: 0 1px 2px rgba(0,0,0,0.05);' : 'background: transparent; color: #64748b;' }}">
                    Already Processed ({{ $summary['already_processed_count'] }})
                </button>
                @if($summary['ineligible_count'] > 0)
                    <button 
                        type="button" 
                        wire:click="setStatusFilter('ineligible')"
                        style="border: none; cursor: pointer; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600; border-radius: 0.375rem; transition: all 0.15s ease; {{ $this->statusFilter === 'ineligible' ? 'background: #ffffff; color: #dc2626; box-shadow: 0 1px 2px rgba(0,0,0,0.05);' : 'background: transparent; color: #64748b;' }}">
                        Skipped ({{ $summary['ineligible_count'] }})
                    </button>
                @endif
            </div>

            <!-- Search & Quick Selection -->
            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <input 
                    type="text" 
                    wire:model.live.debounce.300ms="search" 
                    placeholder="Search property or owner..."
                    style="font-size: 0.8125rem; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.375rem 0.75rem; width: 14rem;"
                />

                <div style="display: flex; gap: 0.375rem;">
                    <button 
                        type="button" 
                        wire:click="selectAllReady"
                        style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.375rem 0.625rem; font-size: 0.75rem; font-weight: 600; color: #334155; cursor: pointer;">
                        Select All Ready
                    </button>
                    <button 
                        type="button" 
                        wire:click="deselectAll"
                        style="background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.375rem 0.625rem; font-size: 0.75rem; font-weight: 600; color: #334155; cursor: pointer;">
                        Deselect All
                    </button>
                </div>
            </div>
        </div>

        <!-- Interactive Property Table -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.8125rem;">
                    <thead>
                        <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.025em;">
                            <th style="padding: 0.75rem 1rem; width: 3rem; text-align: center;">
                                <input 
                                    type="checkbox" 
                                    @click="$wire.selectedProperties.length === {{ $summary['ready_count'] }} ? $wire.deselectAll() : $wire.selectAllReady()"
                                    :checked="$wire.selectedProperties.length > 0 && $wire.selectedProperties.length === {{ $summary['ready_count'] }}"
                                    style="border-radius: 0.25rem; cursor: pointer;"
                                />
                            </th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700;">Property & Owner</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700;">Billing Period</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; text-align: right;">Gross Rent</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; text-align: right;">Fee</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; text-align: right;">Maint. / Advance</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; text-align: right;">Net Payout</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; text-align: center;">Status</th>
                            <th style="padding: 0.75rem 1rem; font-weight: 700; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($paginatedItems as $item)
                            @php
                                $propId = (string) $item['property_id'];
                                $isSelected = in_array($propId, $selectedIds, true);
                                $isReady = $item['status'] === 'ready';
                            @endphp
                            <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease; {{ $isSelected ? 'background: #f0fdf4;' : '' }}"
                                onmouseover="this.style.background='{{ $isSelected ? '#dcfce7' : '#f8fafc' }}'"
                                onmouseout="this.style.background='{{ $isSelected ? '#f0fdf4' : 'transparent' }}'">
                                <!-- Checkbox -->
                                <td style="padding: 0.875rem 1rem; text-align: center;">
                                    @if($isReady)
                                        <input 
                                            type="checkbox" 
                                            wire:click="toggleProperty('{{ $propId }}')"
                                            {{ $isSelected ? 'checked' : '' }}
                                            style="border-radius: 0.25rem; cursor: pointer;"
                                        />
                                    @else
                                        <span style="color: #cbd5e1;">—</span>
                                    @endif
                                </td>

                                <!-- Property & Owner Details -->
                                <td style="padding: 0.875rem 1rem;">
                                    <button 
                                        type="button" 
                                        @click="activeRowDetails = @js($item)"
                                        title="Click to view full payout calculation and links"
                                        style="background: transparent; border: none; padding: 0; text-align: left; cursor: pointer; font-weight: 700; color: #4f46e5; font-size: 0.875rem; text-decoration: underline;">
                                        {{ $item['property_name'] }}
                                    </button>
                                    <div style="font-size: 0.75rem; color: #475569; margin-top: 0.125rem;">
                                        👤 <strong>{{ $item['owner_name'] }}</strong>
                                        <span style="color: #94a3b8; margin: 0 0.25rem;">•</span>
                                        <span style="color: #64748b;">{{ $item['agreement_code'] }}</span>
                                    </div>
                                    <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.125rem;">
                                        🏦 {{ $item['bank_details_formatted'] }}
                                    </div>
                                </td>

                                <!-- Billing Period & Proration -->
                                <td style="padding: 0.875rem 1rem;">
                                    <div style="font-weight: 600; color: #1e293b;">
                                        {{ $item['formatted_period'] ?? 'Full Month' }}
                                    </div>
                                    @if($item['is_prorated'])
                                        <span style="display: inline-block; margin-top: 0.25rem; background: #fef3c7; color: #92400e; font-size: 0.6875rem; font-weight: 700; padding: 0.125rem 0.375rem; border-radius: 0.25rem; border: 1px solid #fde68a;">
                                            ★ Prorated ({{ $item['days_active'] }}/{{ $item['total_days_in_month'] }}d)
                                        </span>
                                    @else
                                        <span style="display: inline-block; margin-top: 0.25rem; background: #f1f5f9; color: #475569; font-size: 0.6875rem; font-weight: 500; padding: 0.125rem 0.375rem; border-radius: 0.25rem;">
                                            Standard Cycle
                                        </span>
                                    @endif
                                </td>

                                <!-- Gross Rent -->
                                <td style="padding: 0.875rem 1rem; text-align: right; font-weight: 600; color: #0f172a;">
                                    ₹{{ number_format($item['gross_rent'], 2) }}
                                </td>

                                <!-- Management Fee -->
                                <td style="padding: 0.875rem 1rem; text-align: right; font-weight: 600; color: #dc2626;">
                                    -₹{{ number_format($item['management_fee'], 2) }}
                                </td>

                                <!-- Maintenance & Advance Offset -->
                                <td style="padding: 0.875rem 1rem; text-align: right;">
                                    <div style="font-weight: 600; color: #d97706;">
                                        -₹{{ number_format($item['advance_offset'], 2) }}
                                    </div>
                                    @if(!empty($item['maintenance_invoices']))
                                        <div style="display: flex; flex-direction: column; gap: 0.125rem; align-items: flex-end; margin-top: 0.25rem;">
                                            @foreach($item['maintenance_invoices'] as $m)
                                                <span style="font-size: 0.6875rem; background: #fffdf5; border: 1px solid #fde68a; color: #92400e; padding: 0.1rem 0.25rem; border-radius: 0.25rem;">
                                                    🔧 {{ $m['ticket_number'] }}: ₹{{ number_format($m['amount'], 2) }}
                                                </span>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>

                                <!-- Net Payout -->
                                <td style="padding: 0.875rem 1rem; text-align: right;">
                                    <div style="font-size: 0.9375rem; font-weight: 800; color: #16a34a;">
                                        ₹{{ number_format($item['net_payout'], 2) }}
                                    </div>
                                </td>

                                <!-- Status Badge -->
                                <td style="padding: 0.875rem 1rem; text-align: center;">
                                    @if($item['status'] === 'ready')
                                        <span style="background: #dcfce7; color: #166534; font-size: 0.75rem; font-weight: 700; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #bbf7d0;">
                                            Ready to Disburse
                                        </span>
                                    @elseif($item['status'] === 'already_processed')
                                        <span style="background: #dbeafe; color: #1e40af; font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #bfdbfe;">
                                            Paid & Processed
                                        </span>
                                    @else
                                        <span style="background: #f1f5f9; color: #64748b; font-size: 0.75rem; font-weight: 500; padding: 0.25rem 0.625rem; border-radius: 9999px;">
                                            {{ $item['status_label'] }}
                                        </span>
                                    @endif
                                </td>

                                <!-- Action Column -->
                                <td style="padding: 0.875rem 1rem; text-align: right;">
                                    <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.375rem;">
                                        <button 
                                            type="button" 
                                            @click="activeRowDetails = @js($item)"
                                            title="View Payout Breakdown & Resource Links"
                                            style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; font-weight: 600; color: #475569; background: #f8fafc; border: 1px solid #cbd5e1; padding: 0.25rem 0.5rem; border-radius: 0.375rem; cursor: pointer; transition: all 0.15s ease;">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 0.875rem; height: 0.875rem;">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                            <span>Details</span>
                                        </button>

                                        @if($isReady)
                                            <button 
                                                type="button" 
                                                wire:click="processSinglePayout('{{ $propId }}')"
                                                style="font-size: 0.6875rem; font-weight: 700; color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; padding: 0.25rem 0.5rem; border-radius: 0.375rem; cursor: pointer;">
                                                ⚡ Disburse
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" style="padding: 3rem; text-align: center; color: #64748b;">
                                    <div style="font-size: 1.5rem; margin-bottom: 0.5rem;">🏢</div>
                                    <div style="font-weight: 600; color: #0f172a;">No properties found matching current filter</div>
                                    <div style="font-size: 0.75rem; margin-top: 0.25rem;">Try adjusting the month/year cycle or search terms.</div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.875rem 1.25rem; background: #f8fafc; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 0.75rem;">
                <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                    <span style="font-size: 0.75rem; color: #64748b;">
                        Showing <strong>{{ $paginatedItems->firstItem() ?? 0 }}</strong> to <strong>{{ $paginatedItems->lastItem() ?? 0 }}</strong> of <strong>{{ $paginatedItems->total() }}</strong> properties
                    </span>
                    <div style="display: flex; align-items: center; gap: 0.375rem;">
                        <span style="font-size: 0.75rem; color: #64748b;">Per page:</span>
                        <select wire:model.live="perPage" style="font-size: 0.75rem; border: 1px solid #cbd5e1; border-radius: 0.25rem; padding: 0.15rem 0.375rem; background: #ffffff;">
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                            <option value="100">100</option>
                            <option value="250">250</option>
                        </select>
                    </div>
                </div>

                @if($paginatedItems->hasPages())
                    <div style="display: flex; align-items: center; gap: 0.25rem;">
                        @if($paginatedItems->onFirstPage())
                            <span style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #94a3b8; border: 1px solid #e2e8f0; border-radius: 0.25rem; background: #ffffff; cursor: not-allowed;">Prev</span>
                        @else
                            <button type="button" wire:click="previousPage" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #0f172a; border: 1px solid #cbd5e1; border-radius: 0.25rem; background: #ffffff; cursor: pointer;">Prev</button>
                        @endif

                        <span style="font-size: 0.75rem; color: #475569; padding: 0 0.5rem; font-weight: 600;">
                            Page {{ $paginatedItems->currentPage() }} of {{ $paginatedItems->lastPage() }}
                        </span>

                        @if($paginatedItems->hasMorePages())
                            <button type="button" wire:click="nextPage" style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #0f172a; border: 1px solid #cbd5e1; border-radius: 0.25rem; background: #ffffff; cursor: pointer;">Next</button>
                        @else
                            <span style="padding: 0.25rem 0.5rem; font-size: 0.75rem; color: #94a3b8; border: 1px solid #e2e8f0; border-radius: 0.25rem; background: #ffffff; cursor: not-allowed;">Next</span>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        <!-- Sticky Bottom Action Bar -->
        <div style="position: sticky; bottom: 1rem; background: #0f172a; color: #ffffff; border-radius: 0.75rem; padding: 1rem 1.5rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.3); z-index: 20;">
            <div style="display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap;">
                <div>
                    <span style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">Selected Properties</span>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #ffffff;">
                        {{ $selectedSummary['count'] }} / {{ $summary['ready_count'] }}
                    </div>
                </div>
                <div style="height: 2rem; width: 1px; background: #334155;"></div>
                <div>
                    <span style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">Total Net Outflow</span>
                    <div style="font-size: 1.25rem; font-weight: 800; color: #4ade80;">
                        ₹{{ number_format($selectedSummary['total_net_payout'], 2) }}
                    </div>
                </div>
                <div style="height: 2rem; width: 1px; background: #334155;"></div>
                <div>
                    <span style="font-size: 0.75rem; color: #94a3b8; text-transform: uppercase;">Dwelly Fee Revenue</span>
                    <div style="font-size: 1.125rem; font-weight: 700; color: #fbbf24;">
                        ₹{{ number_format($selectedSummary['total_management_fee'], 2) }}
                    </div>
                </div>
            </div>

            <div style="display: flex; gap: 0.75rem;">
                <button 
                    type="button" 
                    @click="confirmModalOpen = true"
                    {{ $selectedSummary['count'] === 0 ? 'disabled' : '' }}
                    style="background: {{ $selectedSummary['count'] > 0 ? '#16a34a' : '#475569' }}; color: #ffffff; border: none; border-radius: 0.5rem; padding: 0.625rem 1.25rem; font-size: 0.875rem; font-weight: 700; cursor: {{ $selectedSummary['count'] > 0 ? 'pointer' : 'not-allowed' }}; display: flex; align-items: center; gap: 0.5rem; transition: background 0.15s ease;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1.125rem; height: 1.125rem;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                    </svg>
                    Disburse Selected ({{ $selectedSummary['count'] }} Payouts)
                </button>
            </div>
        </div>

        <!-- Confirmation Modal -->
        <div 
            x-show="confirmModalOpen" 
            style="position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; z-index: 50; padding: 1rem;"
            x-cloak
            x-transition>
            <div 
                @click.away="confirmModalOpen = false"
                style="background: #ffffff; border-radius: 1rem; max-width: 32rem; width: 100%; padding: 1.5rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
                <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1rem;">
                    <div style="width: 2.75rem; height: 2.75rem; border-radius: 9999px; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1.5rem; height: 1.5rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin: 0;">
                            Confirm Batch Owner Payouts
                        </h3>
                        <p style="font-size: 0.8125rem; color: #64748b; margin: 0.25rem 0 0 0;">
                            Billing Cycle: <strong>{{ $monthName }}</strong>
                        </p>
                    </div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1.25rem; font-size: 0.8125rem;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: #64748b;">Properties to Disburse:</span>
                        <strong style="color: #0f172a;">{{ $selectedSummary['count'] }} Properties</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: #64748b;">Total Gross Rent:</span>
                        <span style="color: #0f172a; font-weight: 600;">₹{{ number_format($selectedSummary['total_gross_rent'], 2) }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: #64748b;">Total Commission Invoiced:</span>
                        <span style="color: #d97706; font-weight: 600;">-₹{{ number_format($selectedSummary['total_management_fee'], 2) }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <span style="color: #64748b;">Maintenance Offsets Settled:</span>
                        <span style="color: #dc2626; font-weight: 600;">-₹{{ number_format($selectedSummary['total_advance_offset'], 2) }}</span>
                    </div>
                    <div style="border-top: 1px solid #cbd5e1; padding-top: 0.5rem; display: flex; justify-content: space-between; font-size: 0.9375rem;">
                        <strong style="color: #0f172a;">Total Net Cash Disbursement:</strong>
                        <strong style="color: #16a34a;">₹{{ number_format($selectedSummary['total_net_payout'], 2) }}</strong>
                    </div>
                </div>

                <p style="font-size: 0.75rem; color: #64748b; margin-bottom: 1.25rem; line-height: 1.4;">
                    ⚠️ This operation will record bank outflow transactions, create official Owner Charges Tax Invoices, settle included maintenance invoices, and compile immutable Payout Statement PDFs.
                </p>

                <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
                    <button 
                        type="button" 
                        @click="confirmModalOpen = false"
                        style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.5rem 1rem; font-size: 0.8125rem; font-weight: 600; color: #475569; cursor: pointer;">
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        wire:click="disburseSelected"
                        @click="confirmModalOpen = false"
                        style="background: #16a34a; border: none; border-radius: 0.375rem; padding: 0.5rem 1.25rem; font-size: 0.8125rem; font-weight: 700; color: #ffffff; cursor: pointer;">
                        Yes, Disburse Payouts
                    </button>
                </div>
            </div>
        </div>

        <!-- Owner Payout Item Breakdown & Links Modal -->
        <div 
            x-show="activeRowDetails !== null" 
            x-cloak 
            style="position: fixed; inset: 0; z-index: 9999; display: flex; align-items: center; justify-content: center; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); padding: 1.5rem;"
            @keydown.escape.window="activeRowDetails = null"
        >
            <div 
                @click.away="activeRowDetails = null"
                style="background: #ffffff; border-radius: 1rem; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2), 0 10px 10px -5px rgba(0, 0, 0, 0.04); max-width: 44rem; width: 100%; max-height: 90vh; overflow-y: auto; border: 1px solid #e2e8f0; animation: fadeIn 0.15s ease-out;"
            >
                <!-- Modal Header -->
                <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: #fafafa; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.5rem; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.375rem; height: 1.375rem;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v8.25m0 0a.75.75 0 0 0 .75.75H3m18-9v.75a.75.75 0 0 0 .75.75h.75m0 0v8.25m0 0a.75.75 0 0 1-.75.75H21M9 12a3 3 0 1 1 6 0 3 3 0 0 1-6 0Z" />
                            </svg>
                        </div>
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <h3 style="font-size: 1.0625rem; font-weight: 800; color: #0f172a; margin: 0;" x-text="'Owner Payout: ' + (activeRowDetails?.property_name || '')"></h3>
                                <template x-if="activeRowDetails?.status === 'ready'">
                                    <span style="font-size: 0.6875rem; font-weight: 700; color: #166534; background: #dcfce7; border: 1px solid #bbf7d0; padding: 0.15rem 0.45rem; border-radius: 9999px;">Ready to Disburse</span>
                                </template>
                                <template x-if="activeRowDetails?.status === 'already_processed'">
                                    <span style="font-size: 0.6875rem; font-weight: 700; color: #1e40af; background: #dbeafe; border: 1px solid #bfdbfe; padding: 0.15rem 0.45rem; border-radius: 9999px;">Paid & Processed</span>
                                </template>
                            </div>
                            <p style="font-size: 0.75rem; color: #64748b; margin: 0.125rem 0 0 0;" x-text="'Owner: ' + (activeRowDetails?.owner_name || '') + ' • ' + (activeRowDetails?.formatted_period || '')"></p>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        @click="activeRowDetails = null"
                        style="background: #ffffff; border: 1px solid #cbd5e1; border-radius: 0.375rem; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; font-size: 1rem; color: #64748b; cursor: pointer; transition: all 0.15s ease;"
                        onmouseover="this.style.background='#f1f5f9'"
                        onmouseout="this.style.background='#ffffff'"
                    >
                        ✕
                    </button>
                </div>

                <!-- Modal Body -->
                <template x-if="activeRowDetails">
                    <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem;">
                        
                        <!-- Quick Resource Navigation Links -->
                        <div>
                            <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                                Quick Resource Navigation
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <a 
                                    :href="activeRowDetails.property_url" 
                                    target="_blank"
                                    style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 0.5rem; text-decoration: none; color: #1e293b; transition: all 0.15s ease;"
                                    onmouseover="this.style.background='#f0fdf4'; this.style.borderColor='#86efac';"
                                    onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1';"
                                >
                                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                                        <span style="font-size: 1rem;">🏢</span>
                                        <div>
                                            <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a;" x-text="activeRowDetails.property_name"></div>
                                            <div style="font-size: 0.6875rem; color: #64748b;">Property Record</div>
                                        </div>
                                    </div>
                                    <span style="font-size: 0.75rem; font-weight: 600; color: #16a34a;">Open ↗</span>
                                </a>

                                <template x-if="activeRowDetails.agreement_url">
                                    <a 
                                        :href="activeRowDetails.agreement_url" 
                                        target="_blank"
                                        style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 0.5rem; text-decoration: none; color: #1e293b; transition: all 0.15s ease;"
                                        onmouseover="this.style.background='#eef2ff'; this.style.borderColor='#a5b4fc';"
                                        onmouseout="this.style.background='#f8fafc'; this.style.borderColor='#cbd5e1';"
                                    >
                                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                                            <span style="font-size: 1rem;">📄</span>
                                            <div>
                                                <div style="font-size: 0.8125rem; font-weight: 700; color: #4f46e5;" x-text="activeRowDetails.agreement_code"></div>
                                                <div style="font-size: 0.6875rem; color: #64748b;">Active Tenancy</div>
                                            </div>
                                        </div>
                                        <span style="font-size: 0.75rem; font-weight: 600; color: #4f46e5;">Open ↗</span>
                                    </a>
                                </template>
                            </div>
                        </div>

                        <!-- Owner & Bank Details -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem; background: #f8fafc; padding: 0.875rem 1rem; border-radius: 0.625rem; border: 1px solid #e2e8f0;">
                            <div>
                                <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Beneficiary Owner</span>
                                <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin-top: 0.125rem;" x-text="activeRowDetails.owner_name"></div>
                            </div>
                            <div>
                                <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Banking Account</span>
                                <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin-top: 0.125rem;" x-text="activeRowDetails.bank_details_formatted"></div>
                            </div>
                            <div>
                                <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Handover Date</span>
                                <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin-top: 0.125rem;" x-text="activeRowDetails.handover_date_formatted || 'N/A'"></div>
                            </div>
                            <div>
                                <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Disbursement Period</span>
                                <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin-top: 0.125rem;" x-text="activeRowDetails.formatted_period"></div>
                            </div>
                        </div>

                        <!-- Itemized Financial Calculation Box -->
                        <div style="border: 1px solid #e2e8f0; border-radius: 0.625rem; overflow: hidden;">
                            <div style="background: #f8fafc; padding: 0.625rem 1rem; font-size: 0.75rem; font-weight: 700; color: #475569; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.025em; display: flex; align-items: center; justify-content: space-between;">
                                <span>Disbursement Statement Breakdown</span>
                                <span style="font-size: 0.6875rem; font-weight: 400; color: #64748b;">(INR ₹)</span>
                            </div>
                            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">
                                
                                <!-- Gross Rent Collected -->
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem;">
                                    <div>
                                        <div style="font-weight: 600; color: #0f172a; display: flex; align-items: center; gap: 0.375rem;">
                                            <span>Gross Rent Collected</span>
                                            <template x-if="activeRowDetails.is_prorated">
                                                <span style="font-size: 0.6875rem; font-weight: 700; color: #d97706; background: #fffbeb; border: 1px solid #fde68a; padding: 0.1rem 0.35rem; border-radius: 0.25rem;" x-text="'⚡ Prorated (' + activeRowDetails.days_active + '/' + activeRowDetails.total_days_in_month + ' days)'"></span>
                                            </template>
                                        </div>
                                    </div>
                                    <span style="font-weight: 700; color: #0f172a;" x-text="'₹' + Number(activeRowDetails.gross_rent).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                                </div>

                                <!-- Dwelly Management Fee -->
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem;">
                                    <div>
                                        <span style="font-weight: 600; color: #dc2626;">Dwelly Management Fee</span>
                                        <span style="font-size: 0.6875rem; color: #64748b;" x-text="'(' + activeRowDetails.management_fee_percent + '% Invoiced)'"></span>
                                    </div>
                                    <span style="font-weight: 700; color: #dc2626;" x-text="'-₹' + Number(activeRowDetails.management_fee).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                                </div>

                                <!-- Maintenance & Advance Deductions -->
                                <template x-if="activeRowDetails.advance_offset > 0 || (activeRowDetails.maintenance_invoices && activeRowDetails.maintenance_invoices.length > 0)">
                                    <div style="border-top: 1px dashed #e2e8f0; padding-top: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="font-size: 0.6875rem; font-weight: 700; color: #d97706; text-transform: uppercase; letter-spacing: 0.025em;">
                                            Owner-Payable Deductions & Maintenance Offsets
                                        </div>
                                        <template x-for="m in activeRowDetails.maintenance_invoices" :key="m.id">
                                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem; background: #fffdf5; padding: 0.375rem 0.625rem; border-radius: 0.375rem; border: 1px solid #fde68a;">
                                                <div style="display: flex; align-items: center; gap: 0.375rem;">
                                                    <span style="font-weight: 700; color: #92400e;" x-text="'🔧 ' + m.ticket_number + ':'"></span>
                                                    <span style="color: #78350f;" x-text="m.title"></span>
                                                </div>
                                                <span style="font-weight: 700; color: #d97706;" x-text="'-₹' + Number(m.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <!-- Net Cash Disbursement -->
                                <div style="border-top: 1px solid #cbd5e1; padding-top: 0.75rem; display: flex; align-items: center; justify-content: space-between; font-size: 1.0625rem; font-weight: 800;">
                                    <span style="color: #0f172a;">Net Outflow to Beneficiary</span>
                                    <span style="color: #16a34a;" x-text="'₹' + Number(activeRowDetails.net_payout).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Footer Actions -->
                        <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-top: 0.5rem;">
                            <button 
                                type="button" 
                                @click="activeRowDetails = null"
                                style="padding: 0.5rem 1rem; font-size: 0.8125rem; font-weight: 600; color: #64748b; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 0.5rem; cursor: pointer; transition: all 0.15s ease;"
                                onmouseover="this.style.background='#f1f5f9'"
                                onmouseout="this.style.background='#f8fafc'"
                            >
                                Close
                            </button>

                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <template x-if="activeRowDetails.status === 'ready'">
                                    <button 
                                        type="button" 
                                        @click="$wire.processSinglePayout(activeRowDetails.property_id); activeRowDetails = null;"
                                        style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 1.25rem; font-size: 0.8125rem; font-weight: 700; color: #ffffff; background: #16a34a; border: none; border-radius: 0.5rem; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.1); transition: all 0.15s ease;"
                                        onmouseover="this.style.background='#15803d'"
                                        onmouseout="this.style.background='#16a34a'"
                                    >
                                        <span>⚡ Disburse Payout Now</span>
                                    </button>
                                </template>
                            </div>
                        </div>

                    </div>
                </template>
            </div>
        </div>
    </div>
</x-filament-panels::page>
