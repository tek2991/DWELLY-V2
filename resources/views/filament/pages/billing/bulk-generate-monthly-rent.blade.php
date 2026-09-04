<x-filament-panels::page>
    @php
        $preview = $this->getPreviewData();
        $summary = $preview['summary'];
        $filteredItems = $this->getFilteredItems();
        $paginatedItems = $this->getPaginatedItems();
        $selectedSummary = $this->getSelectedSummary();
        $properties = $this->getPropertiesProperty();
        $monthName = $preview['month_name'];
        $selectedIds = array_map('strval', $this->selectedAgreements);

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

        <!-- Post-Generation Summary Banner (If just executed) -->
        @if($this->lastGenerationSummary)
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
                                Successfully Generated {{ $this->lastGenerationSummary['count'] }} Rent Demands
                            </h3>
                            <p style="font-size: 0.8125rem; color: #15803d; margin: 0.25rem 0 0 0;">
                                Total posted demand: <strong>₹{{ number_format($this->lastGenerationSummary['total_amount'], 2) }}</strong> for <strong>{{ $monthName }}</strong>. All entries have been committed to tenant AR and owner pass-through AP ledger.
                            </p>
                        </div>
                    </div>
                    <button 
                        type="button" 
                        wire:click="dismissGenerationSummary"
                        style="background: transparent; border: none; color: #15803d; cursor: pointer; font-size: 0.8125rem; font-weight: 600;">
                        ✕ Dismiss
                    </button>
                </div>

                @if(!empty($this->lastGenerationSummary['generated_invoices']))
                    <div style="margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                        @foreach($this->lastGenerationSummary['generated_invoices'] as $gen)
                            <a 
                                href="{{ \App\Filament\Resources\Billing\RentDemandsResource::getUrl('index') }}?tableSearch={{ urlencode($gen['invoice_number']) }}"
                                target="_blank"
                                style="display: inline-flex; align-items: center; gap: 0.375rem; background: #ffffff; border: 1px solid #bbf7d0; border-radius: 0.375rem; padding: 0.375rem 0.625rem; font-size: 0.75rem; font-weight: 600; color: #166534; text-decoration: none;">
                                <span>📄 #{{ $gen['invoice_number'] }}</span>
                                <span style="color: #64748b; font-weight: 400;">({{ $gen['tenant_name'] }} - ₹{{ number_format($gen['total_amount'], 2) }})</span>
                            </a>
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
                        style="font-size: 0.75rem; font-weight: 600; color: #4f46e5; background: #eef2ff; border: 1px solid #c7d2fe; padding: 0.375rem 0.75rem; border-radius: 0.375rem; cursor: pointer;">
                        Current Month
                    </button>

                    <!-- Property Filter -->
                    <div style="min-width: 220px;">
                        <select 
                            wire:model.live="propertyId" 
                            style="width: 100%; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.4rem 0.75rem; font-size: 0.8125rem; color: #1e293b; background: #ffffff; outline: none;">
                            <option value="">All Properties ({{ $summary['total_agreements'] }} tenancies)</option>
                            @foreach($properties as $prop)
                                <option value="{{ $prop->id }}">{{ $prop->building_name ?? $prop->name }} ({{ $prop->code }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- Invoicing Dates & Automation Settings -->
                <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <label style="font-size: 0.75rem; font-weight: 600; color: #64748b;">Issue Date:</label>
                        <input 
                            type="date" 
                            wire:model="issueDate" 
                            style="border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.3rem 0.5rem; font-size: 0.8125rem; color: #1e293b; outline: none;"
                        />
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <label style="font-size: 0.75rem; font-weight: 600; color: #64748b;">Due Date:</label>
                        <input 
                            type="date" 
                            wire:model="dueDate" 
                            style="border: 1px solid #cbd5e1; border-radius: 0.375rem; padding: 0.3rem 0.5rem; font-size: 0.8125rem; color: #1e293b; outline: none;"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- KPI Metrics Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.875rem;">
            <!-- Metric 1: Total Agreements -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Active Tenancies</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">
                            {{ $summary['total_agreements'] }}
                        </div>
                    </div>
                    <div style="width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; background: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.75rem; color: #64748b;">
                    Period: <strong style="color: #0f172a;">{{ $monthName }}</strong>
                </div>
            </div>

            <!-- Metric 2: Ready to Generate -->
            <div style="background: #ffffff; border: 1px solid #bbf7d0; border-radius: 0.75rem; padding: 1rem 1.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.6875rem; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.05em;">Ready to Generate</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: #15803d; margin-top: 0.25rem;">
                            {{ $summary['ready_count'] }}
                        </div>
                    </div>
                    <div style="width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.75rem; font-weight: 700; color: #15803d;">
                    Total Inflows: ₹{{ number_format($summary['total_ready_amount'], 2) }}
                </div>
            </div>

            <!-- Metric 2: Base Rent Inflows -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Base Rent Subtotal</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">
                            ₹{{ number_format($summary['total_base_rent'] ?? 0, 2) }}
                        </div>
                    </div>
                    <div style="width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; background: #f1f5f9; color: #475569; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v8.25m0 0a.75.75 0 0 0 .75.75H3m18-9v.75a.75.75 0 0 0 .75.75h.75m0 0v8.25m0 0a.75.75 0 0 1-.75.75H21M9 12a3 3 0 1 1 6 0 3 3 0 0 1-6 0Z" />
                        </svg>
                    </div>
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.75rem; color: #64748b;">
                    Period: <strong style="color: #0f172a;">{{ $monthName }}</strong>
                </div>
            </div>

            <!-- Metric 3: Maintenance Recoveries -->
            <div style="background: #ffffff; border: 1px solid #fed7aa; border-radius: 0.75rem; padding: 1rem 1.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.6875rem; font-weight: 700; color: #9a3412; text-transform: uppercase; letter-spacing: 0.05em;">Tenant Maint. Add-ons</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: #ea580c; margin-top: 0.25rem;">
                            ₹{{ number_format($summary['total_maintenance_amount'] ?? 0, 2) }}
                        </div>
                    </div>
                    <div style="width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; background: #ffedd5; color: #ea580c; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 9.75v1.414l5.657 5.657m0 0L10 14.75" />
                        </svg>
                    </div>
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.75rem; color: #9a3412;">
                    Itemized on consolidated rent notice
                </div>
            </div>

            <!-- Metric 4: Already Generated -->
            <div style="background: #ffffff; border: 1px solid #bfdbfe; border-radius: 0.75rem; padding: 1rem 1.25rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.6875rem; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.05em;">Already Invoiced</div>
                        <div style="font-size: 1.5rem; font-weight: 800; color: #1d4ed8; margin-top: 0.25rem;">
                            {{ $summary['already_generated_count'] }}
                        </div>
                    </div>
                    <div style="width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; background: #dbeafe; color: #2563eb; display: flex; align-items: center; justify-content: center;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.25rem; height: 1.25rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <div style="margin-top: 0.75rem; font-size: 0.75rem; color: #1e40af;">
                    Protected against duplicate billing
                </div>
            </div>
        </div>

        <!-- Main Workspace Table & Execution Bar -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            
            <!-- Toolbar & Filter Controls -->
            <div style="padding: 1.25rem 1.5rem; border-bottom: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 1rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                    
                    <!-- Search Input -->
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1; min-width: 280px; max-width: 420px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.45rem 0.75rem;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1rem; height: 1rem; color: #94a3b8;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="search" 
                            placeholder="Search by tenant, property, or agreement code..." 
                            style="width: 100%; border: none; background: transparent; font-size: 0.8125rem; color: #1e293b; outline: none;"
                        />
                        @if(!empty($this->search))
                            <button type="button" wire:click="$set('search', '')" style="background: transparent; border: none; color: #94a3b8; cursor: pointer; font-size: 0.75rem;">✕</button>
                        @endif
                    </div>

                    <!-- Batch Action Execution Summary & Button -->
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.375rem 0.75rem;">
                            <span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Selected:</span>
                            <span style="font-size: 0.8125rem; font-weight: 700; color: #15803d;">
                                {{ $selectedSummary['count'] }} / {{ $summary['ready_count'] }}
                            </span>
                            <span style="font-size: 0.75rem; color: #94a3b8;">|</span>
                            <span style="font-size: 0.8125rem; font-weight: 800; color: #0f172a;">
                                ₹{{ number_format($selectedSummary['total_amount'], 2) }}
                            </span>
                        </div>

                        <button 
                            type="button" 
                            wire:click="generateSelected" 
                            @if($selectedSummary['count'] === 0) disabled @endif
                            style="display: inline-flex; align-items: center; gap: 0.5rem; background: {{ $selectedSummary['count'] > 0 ? '#4f46e5' : '#94a3b8' }}; color: #ffffff; font-weight: 700; font-size: 0.8125rem; padding: 0.55rem 1.25rem; border-radius: 0.5rem; border: none; cursor: {{ $selectedSummary['count'] > 0 ? 'pointer' : 'not-allowed' }}; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: all 0.2s ease;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 1.125rem; height: 1.125rem;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m3.75 13.5 10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75Z" />
                            </svg>
                            <span>Generate Selected Demands ({{ $selectedSummary['count'] }})</span>
                        </button>
                    </div>
                </div>

                <!-- Filter Status Pills & Quick Selection Controls -->
                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; pt-1;">
                    <div style="display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap;">
                        <button 
                            type="button" 
                            wire:click="setStatusFilter('all')" 
                            style="font-size: 0.75rem; font-weight: 600; padding: 0.3rem 0.75rem; border-radius: 9999px; cursor: pointer; border: 1px solid {{ $this->statusFilter === 'all' ? '#4f46e5' : '#e2e8f0' }}; background: {{ $this->statusFilter === 'all' ? '#4f46e5' : '#f8fafc' }}; color: {{ $this->statusFilter === 'all' ? '#ffffff' : '#64748b' }};">
                            All Tenancies ({{ count($preview['items']) }})
                        </button>

                        <button 
                            type="button" 
                            wire:click="setStatusFilter('ready')" 
                            style="font-size: 0.75rem; font-weight: 600; padding: 0.3rem 0.75rem; border-radius: 9999px; cursor: pointer; border: 1px solid {{ $this->statusFilter === 'ready' ? '#16a34a' : '#e2e8f0' }}; background: {{ $this->statusFilter === 'ready' ? '#16a34a' : '#f8fafc' }}; color: {{ $this->statusFilter === 'ready' ? '#ffffff' : '#64748b' }};">
                            Ready to Generate ({{ $summary['ready_count'] }})
                        </button>

                        <button 
                            type="button" 
                            wire:click="setStatusFilter('already_generated')" 
                            style="font-size: 0.75rem; font-weight: 600; padding: 0.3rem 0.75rem; border-radius: 9999px; cursor: pointer; border: 1px solid {{ $this->statusFilter === 'already_generated' ? '#2563eb' : '#e2e8f0' }}; background: {{ $this->statusFilter === 'already_generated' ? '#2563eb' : '#f8fafc' }}; color: {{ $this->statusFilter === 'already_generated' ? '#ffffff' : '#64748b' }};">
                            Already Generated ({{ $summary['already_generated_count'] }})
                        </button>

                        <button 
                            type="button" 
                            wire:click="setStatusFilter('ineligible')" 
                            style="font-size: 0.75rem; font-weight: 600; padding: 0.3rem 0.75rem; border-radius: 9999px; cursor: pointer; border: 1px solid {{ $this->statusFilter === 'ineligible' ? '#64748b' : '#e2e8f0' }}; background: {{ $this->statusFilter === 'ineligible' ? '#64748b' : '#f8fafc' }}; color: {{ $this->statusFilter === 'ineligible' ? '#ffffff' : '#64748b' }};">
                            Skipped / Ineligible ({{ $summary['ineligible_count'] }})
                        </button>
                    </div>

                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <button 
                            type="button" 
                            wire:click="selectAllReady" 
                            style="font-size: 0.6875rem; font-weight: 600; color: #d97706; background: #fffbeb; border: 1px solid #fde68a; padding: 0.25rem 0.625rem; border-radius: 0.375rem; cursor: pointer;">
                            Select All Ready ({{ $summary['ready_count'] }})
                        </button>

                        <button 
                            type="button" 
                            wire:click="deselectAll" 
                            style="font-size: 0.6875rem; font-weight: 600; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; padding: 0.25rem 0.625rem; border-radius: 0.375rem; cursor: pointer;">
                            Deselect All
                        </button>
                    </div>
                </div>
            </div>

            <!-- Preview Data Table -->
            <div style="overflow-x: auto;">
                @if(empty($filteredItems))
                    <div style="padding: 3rem 1.5rem; text-align: center; color: #64748b;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 2.5rem; height: 2.5rem; margin: 0 auto 0.75rem auto; color: #cbd5e1;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                        <div style="font-size: 0.9375rem; font-weight: 600; color: #334155;">No tenancy agreements match your filter</div>
                        <p style="font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem;">Try switching status tabs, clearing search, or selecting a different billing month.</p>
                    </div>
                @else
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.8125rem;">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #475569; font-size: 0.6875rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                <th style="padding: 0.75rem 1rem; width: 40px; text-align: center;">
                                    <span style="display: none;">Select</span>
                                </th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700;">Agreement / Code</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700;">Tenant</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700;">Property</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700;">Billing Period</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; text-align: right;">Rent Demand</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700;">Status</th>
                                <th style="padding: 0.75rem 1rem; font-weight: 700; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody style="divide-y: 1px solid #e2e8f0;">
                            @foreach($paginatedItems as $item)
                                @php
                                    $isReady = $item['status'] === 'ready';
                                    $isSelected = in_array((string) $item['agreement_id'], $selectedIds, true);
                                    $rowBg = $isSelected ? '#f0fdf4' : ($isReady ? '#ffffff' : '#f8fafc');
                                @endphp
                                <tr style="border-bottom: 1px solid #e2e8f0; background: {{ $rowBg }}; transition: background 0.15s ease;">
                                    <!-- Selection Checkbox -->
                                    <td style="padding: 0.75rem 1rem; text-align: center;">
                                        @if($isReady)
                                            <input 
                                                type="checkbox" 
                                                wire:click="toggleAgreement('{{ $item['agreement_id'] }}')"
                                                @if($isSelected) checked @endif
                                                style="width: 1rem; height: 1rem; border-radius: 0.25rem; accent-color: #4f46e5; cursor: pointer;"
                                            />
                                        @else
                                            <input 
                                                type="checkbox" 
                                                disabled 
                                                style="width: 1rem; height: 1rem; border-radius: 0.25rem; opacity: 0.3; cursor: not-allowed;"
                                            />
                                        @endif
                                    </td>

                                    <!-- Agreement Code -->
                                    <td style="padding: 0.75rem 1rem;">
                                        <button 
                                            type="button" 
                                            @click="activeRowDetails = @js($item)"
                                            title="Click to view full breakdown and resource links"
                                            style="background: transparent; border: none; padding: 0; text-align: left; cursor: pointer; font-weight: 700; color: #4f46e5; font-family: monospace; text-decoration: underline;">
                                            {{ $item['agreement_code'] }}
                                        </button>
                                        <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.125rem;">
                                            Handover: {{ $item['handover_date_formatted'] }}
                                        </div>
                                    </td>

                                    <!-- Tenant -->
                                    <td style="padding: 0.75rem 1rem;">
                                        <div style="font-weight: 600; color: #1e293b;">
                                            {{ $item['tenant_name'] }}
                                        </div>
                                        <div style="font-size: 0.6875rem; color: #64748b;">
                                            Primary Occupant
                                        </div>
                                    </td>

                                    <!-- Property -->
                                    <td style="padding: 0.75rem 1rem;">
                                        <div style="font-weight: 600; color: #1e293b;">
                                            {{ $item['property_name'] }}
                                        </div>
                                        @if($item['property_code'])
                                            <div style="font-size: 0.6875rem; color: #64748b;">
                                                {{ $item['property_code'] }}
                                            </div>
                                        @endif
                                    </td>

                                    <!-- Billing Period & Proration -->
                                    <td style="padding: 0.75rem 1rem;">
                                        <div style="color: #0f172a; font-weight: 500;">
                                            {{ $item['formatted_period'] }}
                                        </div>
                                        @if($item['is_prorated'])
                                            <div style="margin-top: 0.25rem;">
                                                <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; font-weight: 700; color: #d97706; background: #fffbeb; border: 1px solid #fde68a; padding: 0.125rem 0.375rem; border-radius: 0.25rem;">
                                                    ⚡ Prorated ({{ $item['days_active'] }}/{{ $item['total_days_in_month'] }} days)
                                                </span>
                                            </div>
                                        @else
                                            <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.125rem;">
                                                Full billing month
                                            </div>
                                        @endif
                                    </td>

                                    <!-- Rent Amount Breakdown -->
                                    <td style="padding: 0.75rem 1rem; text-align: right;">
                                        <div style="font-weight: 800; color: #0f172a; font-size: 0.875rem;">
                                            ₹{{ number_format($item['total_amount'], 2) }}
                                        </div>
                                        <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.125rem;">
                                            Base Rent: ₹{{ number_format($item['rent_amount'], 2) }}
                                        </div>
                                        @if(!empty($item['maintenance_invoices']))
                                            <div style="display: flex; flex-direction: column; gap: 0.125rem; align-items: flex-end; margin-top: 0.25rem;">
                                                @foreach($item['maintenance_invoices'] as $m)
                                                    <span style="font-size: 0.6875rem; background: #fffdf5; border: 1px solid #fed7aa; color: #9a3412; padding: 0.1rem 0.25rem; border-radius: 0.25rem;">
                                                        🔧 {{ $m['ticket_number'] }}: +₹{{ number_format($m['amount'], 2) }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>

                                    <!-- Status Column -->
                                    <td style="padding: 0.75rem 1rem;">
                                        @if($item['status'] === 'ready')
                                            <span style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 700; color: #16a34a; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 0.2rem 0.5rem; border-radius: 9999px;">
                                                <span style="width: 0.375rem; height: 0.375rem; border-radius: 9999px; background: #16a34a;"></span>
                                                Ready to Bill
                                            </span>
                                        @elseif($item['status'] === 'already_generated')
                                            <a 
                                                href="{{ \App\Filament\Resources\Billing\RentDemandsResource::getUrl('index') }}?tableSearch={{ urlencode($item['existing_invoice_number'] ?? '') }}"
                                                target="_blank"
                                                style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; font-weight: 700; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; padding: 0.2rem 0.5rem; border-radius: 9999px; text-decoration: none;">
                                                <span>✓ Generated (#{{ $item['existing_invoice_number'] ?? 'N/A' }})</span>
                                            </a>
                                        @else
                                            <span style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.6875rem; font-weight: 600; color: #64748b; background: #f1f5f9; border: 1px solid #e2e8f0; padding: 0.2rem 0.5rem; border-radius: 0.375rem;" title="{{ $item['status_label'] }}">
                                                {{ $item['status_label'] }}
                                            </span>
                                        @endif
                                    </td>

                                    <!-- Action Column -->
                                    <td style="padding: 0.75rem 1rem; text-align: right;">
                                        <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.375rem;">
                                            <button 
                                                type="button" 
                                                @click="activeRowDetails = @js($item)"
                                                title="View Detailed Breakdown & Resource Links"
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
                                                    wire:click="generateSingle('{{ $item['agreement_id'] }}')"
                                                    style="font-size: 0.6875rem; font-weight: 700; color: #4f46e5; background: #eef2ff; border: 1px solid #c7d2fe; padding: 0.25rem 0.5rem; border-radius: 0.375rem; cursor: pointer;">
                                                    ⚡ Generate
                                                </button>
                                            @elseif($item['status'] === 'already_generated')
                                                <a 
                                                    href="{{ \App\Filament\Resources\Billing\RentDemandsResource::getUrl('index') }}?tableSearch={{ urlencode($item['existing_invoice_number'] ?? '') }}"
                                                    target="_blank"
                                                    style="font-size: 0.6875rem; font-weight: 600; color: #64748b; text-decoration: underline;">
                                                    View
                                                </a>
                                            @else
                                                <span style="font-size: 0.6875rem; color: #cbd5e1;">—</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <!-- Footer Pagination & Summary Bar -->
            @if(!empty($filteredItems))
                <div style="padding: 0.875rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; font-size: 0.75rem; color: #64748b;">
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                        <span>
                            Showing <strong>{{ $paginatedItems->firstItem() ?? 0 }}</strong> to <strong>{{ $paginatedItems->lastItem() ?? 0 }}</strong> of <strong>{{ $paginatedItems->total() }}</strong> tenancies for <strong>{{ $monthName }}</strong>
                        </span>
                        <div style="display: flex; align-items: center; gap: 0.375rem;">
                            <span>Per page:</span>
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
            @endif
        </div>

        <!-- Item Breakdown & Resource Links Modal -->
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
                        <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.5rem; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.375rem; height: 1.375rem;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                        </div>
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <h3 style="font-size: 1.0625rem; font-weight: 800; color: #0f172a; margin: 0;" x-text="'Rent Demand: ' + (activeRowDetails?.agreement_code || '')"></h3>
                                <template x-if="activeRowDetails?.status === 'ready'">
                                    <span style="font-size: 0.6875rem; font-weight: 700; color: #16a34a; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 0.15rem 0.45rem; border-radius: 9999px;">Ready to Bill</span>
                                </template>
                                <template x-if="activeRowDetails?.status === 'already_generated'">
                                    <span style="font-size: 0.6875rem; font-weight: 700; color: #2563eb; background: #eff6ff; border: 1px solid #bfdbfe; padding: 0.15rem 0.45rem; border-radius: 9999px;" x-text="'✓ Invoiced (#' + (activeRowDetails?.existing_invoice_number || '') + ')'"></span>
                                </template>
                            </div>
                            <p style="font-size: 0.75rem; color: #64748b; margin: 0.125rem 0 0 0;" x-text="(activeRowDetails?.property_name || '') + ' • ' + (activeRowDetails?.formatted_period || '')"></p>
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
                                            <div style="font-size: 0.6875rem; color: #64748b;">Tenancy Agreement</div>
                                        </div>
                                    </div>
                                    <span style="font-size: 0.75rem; font-weight: 600; color: #4f46e5;">Open ↗</span>
                                </a>

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
                                            <div style="font-size: 0.6875rem; color: #64748b;" x-text="activeRowDetails.property_code || 'Property Record'"></div>
                                        </div>
                                    </div>
                                    <span style="font-size: 0.75rem; font-weight: 600; color: #16a34a;">Open ↗</span>
                                </a>
                            </div>
                        </div>

                        <!-- Tenancy & Party Details Grid -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem; background: #f8fafc; padding: 0.875rem 1rem; border-radius: 0.625rem; border: 1px solid #e2e8f0;">
                            <div>
                                <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Tenant</span>
                                <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin-top: 0.125rem;" x-text="activeRowDetails.tenant_name"></div>
                            </div>
                            <div>
                                <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Owner</span>
                                <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin-top: 0.125rem;" x-text="activeRowDetails.owner_name"></div>
                            </div>
                            <div>
                                <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Handover Date</span>
                                <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin-top: 0.125rem;" x-text="activeRowDetails.handover_date_formatted"></div>
                            </div>
                            <div>
                                <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b; text-transform: uppercase;">Billing Period</span>
                                <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin-top: 0.125rem;" x-text="activeRowDetails.formatted_period"></div>
                            </div>
                        </div>

                        <!-- Itemized Financial Calculation Box -->
                        <div style="border: 1px solid #e2e8f0; border-radius: 0.625rem; overflow: hidden;">
                            <div style="background: #f8fafc; padding: 0.625rem 1rem; font-size: 0.75rem; font-weight: 700; color: #475569; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; letter-spacing: 0.025em; display: flex; align-items: center; justify-content: space-between;">
                                <span>Itemized Demand Breakdown</span>
                                <span style="font-size: 0.6875rem; font-weight: 400; color: #64748b;">(INR ₹)</span>
                            </div>
                            <div style="padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">
                                
                                <!-- Base Rent Line -->
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem;">
                                    <div>
                                        <div style="font-weight: 600; color: #0f172a; display: flex; align-items: center; gap: 0.375rem;">
                                            <span>Monthly Rent (Pass-through AP)</span>
                                            <template x-if="activeRowDetails.is_prorated">
                                                <span style="font-size: 0.6875rem; font-weight: 700; color: #d97706; background: #fffbeb; border: 1px solid #fde68a; padding: 0.1rem 0.35rem; border-radius: 0.25rem;" x-text="'⚡ Prorated (' + activeRowDetails.days_active + '/' + activeRowDetails.total_days_in_month + ' days)'"></span>
                                            </template>
                                        </div>
                                        <div style="font-size: 0.6875rem; color: #64748b; margin-top: 0.125rem;" x-text="'Standard Rent: ₹' + Number(activeRowDetails.standard_rent).toLocaleString('en-IN', {minimumFractionDigits: 2})"></div>
                                    </div>
                                    <span style="font-weight: 700; color: #0f172a;" x-text="'₹' + Number(activeRowDetails.rent_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                                </div>

                                <!-- Utility Charges (if any) -->
                                <template x-if="activeRowDetails.utility_amount > 0">
                                    <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem;">
                                        <div style="font-weight: 600; color: #0f172a;">Utility Charges</div>
                                        <span style="font-weight: 700; color: #0f172a;" x-text="'₹' + Number(activeRowDetails.utility_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                                    </div>
                                </template>

                                <!-- Tenant Maintenance Recoveries -->
                                <template x-if="activeRowDetails.maintenance_invoices && activeRowDetails.maintenance_invoices.length > 0">
                                    <div style="border-top: 1px dashed #e2e8f0; padding-top: 0.75rem; display: flex; flex-direction: column; gap: 0.5rem;">
                                        <div style="font-size: 0.6875rem; font-weight: 700; color: #ea580c; text-transform: uppercase; letter-spacing: 0.025em;">
                                            Tenant-Payable Maintenance Recoveries (Itemized)
                                        </div>
                                        <template x-for="m in activeRowDetails.maintenance_invoices" :key="m.id">
                                            <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem; background: #fff7ed; padding: 0.375rem 0.625rem; border-radius: 0.375rem; border: 1px solid #fed7aa;">
                                                <div style="display: flex; align-items: center; gap: 0.375rem;">
                                                    <span style="font-weight: 700; color: #9a3412;" x-text="'🔧 ' + m.ticket_number + ':'"></span>
                                                    <span style="color: #7c2d12;" x-text="m.title"></span>
                                                </div>
                                                <span style="font-weight: 700; color: #ea580c;" x-text="'+₹' + Number(m.amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <!-- Grand Total Line -->
                                <div style="border-top: 1px solid #cbd5e1; padding-top: 0.75rem; display: flex; align-items: center; justify-content: space-between; font-size: 1.0625rem; font-weight: 800;">
                                    <span style="color: #0f172a;">Total Rent Demand Payable</span>
                                    <span style="color: #16a34a;" x-text="'₹' + Number(activeRowDetails.total_amount).toLocaleString('en-IN', {minimumFractionDigits: 2})"></span>
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
                                        @click="$wire.generateSingle(activeRowDetails.agreement_id); activeRowDetails = null;"
                                        style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 1.25rem; font-size: 0.8125rem; font-weight: 700; color: #ffffff; background: #4f46e5; border: none; border-radius: 0.5rem; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.1); transition: all 0.15s ease;"
                                        onmouseover="this.style.background='#4338ca'"
                                        onmouseout="this.style.background='#4f46e5'"
                                    >
                                        <span>⚡ Generate Demand Now</span>
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
