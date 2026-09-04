<x-filament-panels::page>
    @php
        $metrics = $this->getMetrics();
        $counts = $this->getTabCounts();
        $properties = \App\Domain\Property\Models\Property::pluck('building_name', 'id');
    @endphp

    <div style="display: flex; flex-direction: column; gap: 1.25rem; width: 100%;">
        
        <!-- Top Metrics Cards: Sleek Modern Dashboard Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 0.875rem;">
            
            <!-- Card 1: Rent Receivables -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.125rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: all 0.2s ease; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Rent Receivables</div>
                        <div style="font-size: 1.375rem; font-weight: 800; color: #0f172a; margin-top: 0.375rem; letter-spacing: -0.02em;">
                            ₹{{ number_format($metrics['total_rent_due'], 2) }}
                        </div>
                    </div>
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #eef2ff; color: #4f46e5; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.375rem; height: 1.375rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6H2.25m0 0v8.25m0 0a60.073 60.073 0 0 0 15.797 2.101c.727.198 1.453-.342 1.453-1.096V14.25m-17.25 0a60.073 60.073 0 0 1 15.797-2.101c.727-.198 1.453.342 1.453 1.096V6.75A.75.75 0 0 0 19.5 6h-.75m-15 0v.75m15-1.5a.75.75 0 0 0-.75-.75H3.75a.75.75 0 0 0-.75.75m16.5 0v.75m0 0v8.25" />
                        </svg>
                    </div>
                </div>
                <div style="margin-top: 0.875rem; display: flex; align-items: center; gap: 0.375rem;">
                    <span style="display: inline-flex; align-items: center; font-size: 0.6875rem; font-weight: 700; color: {{ $counts['rent_invoices'] > 0 ? '#dc2626' : '#16a34a' }}; background: {{ $counts['rent_invoices'] > 0 ? '#fef2f2' : '#f0fdf4' }}; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                        {{ $counts['rent_invoices'] }} unpaid / partial
                    </span>
                </div>
            </div>

            <!-- Card 2: Maintenance Due -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.125rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: all 0.2s ease; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Maintenance Due</div>
                        <div style="font-size: 1.375rem; font-weight: 800; color: #0f172a; margin-top: 0.375rem; letter-spacing: -0.02em;">
                            ₹{{ number_format($metrics['total_maintenance_due'], 2) }}
                        </div>
                    </div>
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #fffbeb; color: #d97706; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.375rem; height: 1.375rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.017a4.5 4.5 0 0 0 4.544-4.544c.171-.58.147-1.193-.017-1.743l-3.12 3.12a1.5 1.5 0 0 1-2.122-2.122l3.12-3.12a4.498 4.498 0 0 0-4.544 4.544c-.171.58-.147 1.193.017 1.743" />
                        </svg>
                    </div>
                </div>
                <div style="margin-top: 0.875rem; display: flex; align-items: center; gap: 0.375rem;">
                    <span style="display: inline-flex; align-items: center; font-size: 0.6875rem; font-weight: 700; color: {{ $counts['maintenance_invoices'] > 0 ? '#d97706' : '#16a34a' }}; background: {{ $counts['maintenance_invoices'] > 0 ? '#fffbeb' : '#f0fdf4' }}; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                        {{ $counts['maintenance_invoices'] }} pending repair bills
                    </span>
                </div>
            </div>

            <!-- Card 3: Deposits in Custody -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.125rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: all 0.2s ease; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Deposits in Custody</div>
                        <div style="font-size: 1.375rem; font-weight: 800; color: #0f172a; margin-top: 0.375rem; letter-spacing: -0.02em;">
                            ₹{{ number_format($metrics['total_active_deposits'], 2) }}
                        </div>
                    </div>
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #ecfdf5; color: #059669; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.375rem; height: 1.375rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>
                </div>
                <div style="margin-top: 0.875rem; display: flex; align-items: center; gap: 0.375rem;">
                    <span style="display: inline-flex; align-items: center; font-size: 0.6875rem; font-weight: 700; color: #059669; background: #ecfdf5; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                        Across {{ $counts['security_deposits'] }} active leases
                    </span>
                </div>
            </div>

            <!-- Card 4: Move-Out Settlements -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.125rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: all 0.2s ease; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Move-Out Settlements</div>
                        <div style="font-size: 1.375rem; font-weight: 800; color: #0f172a; margin-top: 0.375rem; letter-spacing: -0.02em;">
                            {{ $metrics['pending_moveouts_count'] }}
                        </div>
                    </div>
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #fff1f2; color: #e11d48; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.375rem; height: 1.375rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                        </svg>
                    </div>
                </div>
                <div style="margin-top: 0.875rem; display: flex; align-items: center; gap: 0.375rem;">
                    <span style="display: inline-flex; align-items: center; font-size: 0.6875rem; font-weight: 700; color: {{ $metrics['pending_moveouts_count'] > 0 ? '#e11d48' : '#64748b' }}; background: {{ $metrics['pending_moveouts_count'] > 0 ? '#fff1f2' : '#f1f5f9' }}; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                        {{ $metrics['pending_moveouts_count'] > 0 ? 'Vacating / refund pending' : 'No pending move-outs' }}
                    </span>
                </div>
            </div>

            <!-- Card 5: Vendor Payables -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; padding: 1.125rem 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); transition: all 0.2s ease; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">Vendor Payables</div>
                        <div style="font-size: 1.375rem; font-weight: 800; color: #0f172a; margin-top: 0.375rem; letter-spacing: -0.02em;">
                            ₹{{ number_format($metrics['total_vendor_payables'], 2) }}
                        </div>
                    </div>
                    <div style="width: 2.5rem; height: 2.5rem; border-radius: 0.625rem; background: #faf5ff; color: #9333ea; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.375rem; height: 1.375rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                </div>
                <div style="margin-top: 0.875rem; display: flex; align-items: center; gap: 0.375rem;">
                    <span style="display: inline-flex; align-items: center; font-size: 0.6875rem; font-weight: 700; color: {{ $counts['vendor_payables'] > 0 ? '#9333ea' : '#64748b' }}; background: {{ $counts['vendor_payables'] > 0 ? '#faf5ff' : '#f1f5f9' }}; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                        {{ $counts['vendor_payables'] }} vendor bills pending
                    </span>
                </div>
            </div>

        </div>

        <!-- Sleek Segmented Tab Navigation -->
        <div style="background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.3125rem; display: flex; gap: 0.3125rem; overflow-x: auto;">
            
            <button 
                type="button"
                wire:click="setTab('security_deposits')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4375rem 0.875rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'security_deposits' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'security_deposits' ? '#059669' : '#94a3b8' }};">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                </svg>
                <span>Security Deposits</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'security_deposits' ? 'background: #ecfdf5; color: #059669;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $counts['security_deposits'] }}
                </span>
            </button>

            <button 
                type="button"
                wire:click="setTab('maintenance_invoices')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4375rem 0.875rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'maintenance_invoices' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'maintenance_invoices' ? '#d97706' : '#94a3b8' }};">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63" />
                </svg>
                <span>Maintenance Invoices</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'maintenance_invoices' ? 'background: #fffbeb; color: #d97706;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $counts['maintenance_invoices'] }}
                </span>
            </button>

            <button 
                type="button"
                wire:click="setTab('rent_invoices')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4375rem 0.875rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'rent_invoices' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'rent_invoices' ? '#4f46e5' : '#94a3b8' }};">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                <span>Overdue Rent</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'rent_invoices' ? 'background: #eef2ff; color: #4f46e5;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $counts['rent_invoices'] }}
                </span>
            </button>

            <button 
                type="button"
                wire:click="setTab('vendor_payables')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4375rem 0.875rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'vendor_payables' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'vendor_payables' ? '#9333ea' : '#94a3b8' }};">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12" />
                </svg>
                <span>Vendor Payables</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'vendor_payables' ? 'background: #faf5ff; color: #9333ea;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $counts['vendor_payables'] }}
                </span>
            </button>

            <button 
                type="button"
                wire:click="setTab('owner_advances')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.4375rem 0.875rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'owner_advances' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'owner_advances' ? '#0284c7' : '#94a3b8' }};">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Zm0 3h.008v.008h-.008v-.008Z" />
                </svg>
                <span>Owner Advances</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'owner_advances' ? 'background: #e0f2fe; color: #0369a1;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $counts['owner_advances'] }}
                </span>
            </button>

        </div>

        <!-- Integrated Filter & Search Toolbar -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            
            <!-- Left: Search & Property Filter -->
            <div style="display: flex; align-items: center; gap: 0.625rem; flex: 1; min-width: 300px;">
                <div style="display: flex; align-items: center; gap: 0.5rem; flex: 1; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.4375rem 0.75rem; transition: border-color 0.15s ease;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 0.9375rem; height: 0.9375rem; color: #94a3b8; flex-shrink: 0;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                    <input 
                        type="text"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Search by code, invoice #, tenant, property, or vendor..."
                        style="width: 100%; border: none; outline: none; background: transparent; font-size: 0.8125rem; color: #0f172a;"
                    />
                </div>

                <select 
                    wire:model.live="propertyFilter"
                    style="border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.4375rem 0.75rem; font-size: 0.8125rem; color: #334155; background: #f8fafc; outline: none; cursor: pointer;">
                    <option value="">All Properties</option>
                    @foreach($properties as $propId => $propName)
                        <option value="{{ $propId }}">{{ $propName }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Right: Tab-Specific Primary Actions -->
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                @if($activeTab === 'security_deposits')
                    <button 
                        type="button"
                        wire:click="mountAction('recordDepositReceipt')"
                        style="display: flex; align-items: center; gap: 0.375rem; background: #059669; color: #ffffff; font-size: 0.75rem; font-weight: 600; padding: 0.4375rem 0.875rem; border-radius: 0.5rem; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background-color 0.15s ease;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 0.875rem; height: 0.875rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Record Receipt</span>
                    </button>

                    <button 
                        type="button"
                        wire:click="mountAction('recordDepositPlacement')"
                        style="display: flex; align-items: center; gap: 0.375rem; background: #ffffff; color: #334155; border: 1px solid #cbd5e1; font-size: 0.75rem; font-weight: 600; padding: 0.4375rem 0.875rem; border-radius: 0.5rem; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.02); transition: background-color 0.15s ease;">
                        <span>Place Deposit</span>
                    </button>
                @elseif($activeTab === 'maintenance_invoices' || $activeTab === 'rent_invoices')
                    <button 
                        type="button"
                        wire:click="mountAction('recordInvoicePayment')"
                        style="display: flex; align-items: center; gap: 0.375rem; background: #4f46e5; color: #ffffff; font-size: 0.75rem; font-weight: 600; padding: 0.4375rem 0.875rem; border-radius: 0.5rem; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background-color 0.15s ease;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 0.875rem; height: 0.875rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Record Payment</span>
                    </button>
                @elseif($activeTab === 'vendor_payables')
                    <button 
                        type="button"
                        wire:click="mountAction('recordBillPayment')"
                        style="display: flex; align-items: center; gap: 0.375rem; background: #7c3aed; color: #ffffff; font-size: 0.75rem; font-weight: 600; padding: 0.4375rem 0.875rem; border-radius: 0.5rem; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.05); transition: background-color 0.15s ease;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 0.875rem; height: 0.875rem;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        <span>Settle Bill</span>
                    </button>
                @endif
            </div>

        </div>

        <!-- Sub-filter Filter Pills -->
        <div style="display: flex; align-items: center; gap: 0.375rem; flex-wrap: wrap;">
            @if($activeTab === 'security_deposits')
                <button type="button" wire:click="$set('depositSubFilter', 'all')" style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #e2e8f0; cursor: pointer; {{ $depositSubFilter === 'all' ? 'background: #0f172a; color: #ffffff; border-color: #0f172a;' : 'background: #ffffff; color: #64748b;' }}">All Tenancies</button>
                <button type="button" wire:click="$set('depositSubFilter', 'pending_collection')" style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #fed7aa; cursor: pointer; {{ $depositSubFilter === 'pending_collection' ? 'background: #ea580c; color: #ffffff; border-color: #ea580c;' : 'background: #fff7ed; color: #c2410c;' }}">Pending Collection</button>
                <button type="button" wire:click="$set('depositSubFilter', 'in_custody')" style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #bbf7d0; cursor: pointer; {{ $depositSubFilter === 'in_custody' ? 'background: #059669; color: #ffffff; border-color: #059669;' : 'background: #f0fdf4; color: #15803d;' }}">In Custody (Bank / Placed)</button>
                <button type="button" wire:click="$set('depositSubFilter', 'pending_settlement')" style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #fecaca; cursor: pointer; {{ $depositSubFilter === 'pending_settlement' ? 'background: #e11d48; color: #ffffff; border-color: #e11d48;' : 'background: #fff1f2; color: #be123c;' }}">Pending Move-Out Settlement</button>
            @elseif($activeTab === 'maintenance_invoices')
                <button type="button" wire:click="$set('maintenanceSubFilter', 'all')" style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #e2e8f0; cursor: pointer; {{ $maintenanceSubFilter === 'all' ? 'background: #0f172a; color: #ffffff; border-color: #0f172a;' : 'background: #ffffff; color: #64748b;' }}">All Invoices</button>
                <button type="button" wire:click="$set('maintenanceSubFilter', 'overdue')" style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #fecaca; cursor: pointer; {{ $maintenanceSubFilter === 'overdue' ? 'background: #dc2626; color: #ffffff; border-color: #dc2626;' : 'background: #fef2f2; color: #b91c1c;' }}">Past Due Date</button>
                <button type="button" wire:click="$set('maintenanceSubFilter', 'partially_paid')" style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #fde68a; cursor: pointer; {{ $maintenanceSubFilter === 'partially_paid' ? 'background: #d97706; color: #ffffff; border-color: #d97706;' : 'background: #fffbeb; color: #b45309;' }}">Partially Paid</button>
                <button type="button" wire:click="$set('maintenanceSubFilter', 'unpaid')" style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #bfdbfe; cursor: pointer; {{ $maintenanceSubFilter === 'unpaid' ? 'background: #2563eb; color: #ffffff; border-color: #2563eb;' : 'background: #eff6ff; color: #1d4ed8;' }}">Unpaid (0 Received)</button>
            @elseif($activeTab === 'rent_invoices')
                <button type="button" wire:click="$set('rentSubFilter', 'all')" style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #e2e8f0; cursor: pointer; {{ $rentSubFilter === 'all' ? 'background: #0f172a; color: #ffffff; border-color: #0f172a;' : 'background: #ffffff; color: #64748b;' }}">All Rent Invoices</button>
                <button type="button" wire:click="$set('rentSubFilter', 'overdue')" style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #fecaca; cursor: pointer; {{ $rentSubFilter === 'overdue' ? 'background: #dc2626; color: #ffffff; border-color: #dc2626;' : 'background: #fef2f2; color: #b91c1c;' }}">Overdue Only</button>
                <button type="button" wire:click="$set('rentSubFilter', 'partially_paid')" style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid #fde68a; cursor: pointer; {{ $rentSubFilter === 'partially_paid' ? 'background: #d97706; color: #ffffff; border-color: #d97706;' : 'background: #fffbeb; color: #b45309;' }}">Partially Paid</button>
            @endif
        </div>

        <!-- Tab 1: Security Deposits Table -->
        @if($activeTab === 'security_deposits')
            @php $deposits = $this->getSecurityDeposits(); @endphp
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                @if($deposits->isEmpty())
                    <div style="padding: 3.5rem 2rem; text-align: center; color: #64748b;">
                        <div style="font-size: 1rem; font-weight: 700; color: #334155;">No security deposits found</div>
                        <div style="font-size: 0.8125rem; margin-top: 0.25rem;">Try adjusting your search terms or property filter.</div>
                    </div>
                @else
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.75rem;">
                            <thead>
                                <tr style="background: #f8fafc; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">
                                    <th style="padding: 0.75rem 1rem;">Agreement & Tenant</th>
                                    <th style="padding: 0.75rem 1rem;">Property</th>
                                    <th style="padding: 0.75rem 1rem;">Lease Dates</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Deposit Amount</th>
                                    <th style="padding: 0.75rem 1rem; text-align: center;">Collection Status</th>
                                    <th style="padding: 0.75rem 1rem; text-align: center;">Placement / Custody</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($deposits as $item)
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.15s ease;">
                                        <td style="padding: 0.75rem 1rem;">
                                            <div style="font-weight: 700; color: #0f172a;">{{ $item['tenant_name'] }}</div>
                                            <div style="color: #64748b; font-size: 0.6875rem; font-family: monospace;">{{ $item['code'] }}</div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem;">
                                            <div style="font-weight: 600; color: #334155;">{{ $item['property_name'] }}</div>
                                            <div style="color: #64748b; font-size: 0.6875rem; font-family: monospace;">{{ $item['property_code'] }}</div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #475569;">
                                            <div>{{ $item['start_date'] }} – {{ $item['vacating_date'] }}</div>
                                            @if($item['is_vacating'])
                                                <span style="display: inline-block; background: #fff1f2; color: #e11d48; font-size: 0.625rem; font-weight: 700; padding: 0.1rem 0.375rem; border-radius: 9999px; margin-top: 0.125rem; border: 1px solid #fecdd3;">
                                                    Move-Out Pending
                                                </span>
                                            @endif
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <div style="font-weight: 800; font-size: 0.8125rem; color: #0f172a;">
                                                ₹{{ number_format($item['security_deposit'], 2) }}
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            @if($item['deposit_received'])
                                                <span style="display: inline-block; background: #ecfdf5; color: #059669; font-size: 0.6875rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; border: 1px solid #a7f3d0;">
                                                    ✓ Received
                                                </span>
                                            @else
                                                <span style="display: inline-block; background: #fff7ed; color: #c2410c; font-size: 0.6875rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; border: 1px solid #fed7aa;">
                                                    Pending Collection
                                                </span>
                                            @endif
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            <span style="display: inline-block; background: #f8fafc; color: #475569; font-size: 0.6875rem; font-weight: 600; padding: 0.2rem 0.5rem; border-radius: 0.375rem; border: 1px solid #e2e8f0;">
                                                {{ $item['placement_status'] }}
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.375rem;">
                                                @if(!$item['deposit_received'])
                                                    <button 
                                                        type="button" 
                                                        wire:click="mountAction('recordDepositReceipt', { tenancy_agreement_id: '{{ $item['id'] }}', amount: {{ $item['security_deposit'] }} })"
                                                        style="font-size: 0.6875rem; font-weight: 700; color: #059669; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.25rem 0.5rem; border-radius: 0.375rem; cursor: pointer; transition: background-color 0.15s ease;">
                                                        Receive
                                                    </button>
                                                @else
                                                    <button 
                                                        type="button" 
                                                        wire:click="mountAction('recordDepositPlacement', { tenancy_agreement_id: '{{ $item['id'] }}' })"
                                                        style="font-size: 0.6875rem; font-weight: 600; color: #d97706; background: #fffbeb; border: 1px solid #fde68a; padding: 0.25rem 0.5rem; border-radius: 0.375rem; cursor: pointer;">
                                                        Place
                                                    </button>

                                                    <button 
                                                        type="button" 
                                                        wire:click="mountAction('recordDepositSettlement', { tenancy_agreement_id: '{{ $item['id'] }}' })"
                                                        style="font-size: 0.6875rem; font-weight: 600; color: #e11d48; background: #fff1f2; border: 1px solid #fecdd3; padding: 0.25rem 0.5rem; border-radius: 0.375rem; cursor: pointer;">
                                                        Settle
                                                    </button>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        <!-- Tab 2: Maintenance Invoices Table -->
        @elseif($activeTab === 'maintenance_invoices')
            @php $maintInvoices = $this->getMaintenanceInvoices(); @endphp
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                @if($maintInvoices->isEmpty())
                    <div style="padding: 3.5rem 2rem; text-align: center; color: #64748b;">
                        <div style="font-size: 1rem; font-weight: 700; color: #334155;">No outstanding maintenance invoices</div>
                        <div style="font-size: 0.8125rem; margin-top: 0.25rem;">All maintenance repair bills are fully settled.</div>
                    </div>
                @else
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.75rem;">
                            <thead>
                                <tr style="background: #f8fafc; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">
                                    <th style="padding: 0.75rem 1rem;">Invoice # & Job</th>
                                    <th style="padding: 0.75rem 1rem;">Billed To</th>
                                    <th style="padding: 0.75rem 1rem;">Property</th>
                                    <th style="padding: 0.75rem 1rem;">Due Date</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Total Amount</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Balance Due</th>
                                    <th style="padding: 0.75rem 1rem; text-align: center;">Status</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($maintInvoices as $inv)
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.15s ease;">
                                        <td style="padding: 0.75rem 1rem;">
                                            <div style="font-weight: 700; color: #0f172a;">{{ $inv['invoice_number'] }}</div>
                                            <div style="color: #64748b; font-size: 0.6875rem;">{{ Str::limit($inv['notes'] ?? 'Maintenance Request', 40) }}</div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #334155; font-weight: 600;">
                                            {{ $inv['contact_name'] }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem;">
                                            <div style="font-weight: 600; color: #334155;">{{ $inv['property_name'] }}</div>
                                            <div style="color: #64748b; font-size: 0.6875rem; font-family: monospace;">{{ $inv['property_code'] }}</div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #475569;">
                                            <div>{{ $inv['due_date'] }}</div>
                                            @if($inv['is_overdue'])
                                                <span style="display: inline-block; background: #fff1f2; color: #e11d48; font-size: 0.625rem; font-weight: 700; padding: 0.1rem 0.375rem; border-radius: 9999px; margin-top: 0.125rem; border: 1px solid #fecdd3;">
                                                    Overdue ({{ $inv['overdue_days'] }}d)
                                                </span>
                                            @endif
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right; color: #64748b;">
                                            ₹{{ number_format($inv['grand_total'], 2) }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <div style="font-weight: 800; font-size: 0.8125rem; color: #dc2626;">
                                                ₹{{ number_format($inv['balance_due'], 2) }}
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            <span style="display: inline-block; background: #fffbeb; color: #b45309; font-size: 0.6875rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; border: 1px solid #fde68a;">
                                                {{ ucfirst($inv['status']) }}
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <button 
                                                type="button" 
                                                wire:click="mountAction('recordInvoicePayment', { invoice_id: {{ $inv['id'] }}, amount: {{ $inv['balance_due'] }} })"
                                                style="font-size: 0.6875rem; font-weight: 700; color: #059669; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 0.25rem 0.625rem; border-radius: 0.375rem; cursor: pointer;">
                                                Record Payment
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        <!-- Tab 3: Overdue Rent Demands Table -->
        @elseif($activeTab === 'rent_invoices' || $activeTab === 'rent_demands')
            @php $rentInvoices = $this->getRentDemands(); @endphp
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                @if($rentInvoices->isEmpty())
                    <div style="padding: 3.5rem 2rem; text-align: center; color: #64748b;">
                        <div style="font-size: 1rem; font-weight: 700; color: #334155;">No unpaid rent demands</div>
                        <div style="font-size: 0.8125rem; margin-top: 0.25rem;">All monthly rent demands are fully collected.</div>
                    </div>
                @else
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.75rem;">
                            <thead>
                                <tr style="background: #f8fafc; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">
                                    <th style="padding: 0.75rem 1rem;">Demand #</th>
                                    <th style="padding: 0.75rem 1rem;">Tenant</th>
                                    <th style="padding: 0.75rem 1rem;">Property</th>
                                    <th style="padding: 0.75rem 1rem;">Billing Cycle</th>
                                    <th style="padding: 0.75rem 1rem;">Due Date</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Total Demand</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Outstanding Balance</th>
                                    <th style="padding: 0.75rem 1rem; text-align: center;">Aging</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($rentInvoices as $inv)
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.15s ease;">
                                        <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0f172a;">
                                            {{ $inv['invoice_number'] }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #334155; font-weight: 600;">
                                            {{ $inv['tenant_name'] }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem;">
                                            <div style="font-weight: 600; color: #334155;">{{ $inv['property_name'] }}</div>
                                            <div style="color: #64748b; font-size: 0.6875rem; font-family: monospace;">{{ $inv['property_code'] }}</div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #4f46e5; font-weight: 600;">
                                            {{ $inv['billing_period'] }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #475569;">
                                            {{ $inv['due_date'] }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right; color: #64748b;">
                                            ₹{{ number_format($inv['grand_total'], 2) }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <div style="font-weight: 800; font-size: 0.8125rem; color: #dc2626;">
                                                ₹{{ number_format($inv['balance_due'], 2) }}
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            @if($inv['is_overdue'])
                                                <span style="display: inline-block; background: #fff1f2; color: #e11d48; font-size: 0.625rem; font-weight: 700; padding: 0.1rem 0.375rem; border-radius: 9999px; border: 1px solid #fecdd3;">
                                                    {{ $inv['overdue_days'] }}d Overdue
                                                </span>
                                            @else
                                                <span style="display: inline-block; background: #f0fdf4; color: #16a34a; font-size: 0.625rem; font-weight: 700; padding: 0.1rem 0.375rem; border-radius: 9999px; border: 1px solid #bbf7d0;">
                                                    Current Cycle
                                                </span>
                                            @endif
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <button 
                                                type="button" 
                                                wire:click="mountAction('recordInvoicePayment', { invoice_id: {{ $inv['id'] }}, amount: {{ $inv['balance_due'] }} })"
                                                style="font-size: 0.6875rem; font-weight: 700; color: #4f46e5; background: #eef2ff; border: 1px solid #c7d2fe; padding: 0.25rem 0.625rem; border-radius: 0.375rem; cursor: pointer;">
                                                Collect Rent
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        <!-- Tab 4: Vendor Payables Table -->
        @elseif($activeTab === 'vendor_payables')
            @php $vendorBills = $this->getVendorBills(); @endphp
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                @if($vendorBills->isEmpty())
                    <div style="padding: 3.5rem 2rem; text-align: center; color: #64748b;">
                        <div style="font-size: 1rem; font-weight: 700; color: #334155;">No pending vendor bills</div>
                        <div style="font-size: 0.8125rem; margin-top: 0.25rem;">All contractor and supplier bills are settled.</div>
                    </div>
                @else
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.75rem;">
                            <thead>
                                <tr style="background: #f8fafc; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">
                                    <th style="padding: 0.75rem 1rem;">Bill # & Ref</th>
                                    <th style="padding: 0.75rem 1rem;">Vendor / Contractor</th>
                                    <th style="padding: 0.75rem 1rem;">Bill Date</th>
                                    <th style="padding: 0.75rem 1rem;">Due Date</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Total Bill</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Balance Payable</th>
                                    <th style="padding: 0.75rem 1rem; text-align: center;">Status</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($vendorBills as $bill)
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.15s ease;">
                                        <td style="padding: 0.75rem 1rem;">
                                            <div style="font-weight: 700; color: #0f172a;">{{ $bill['bill_number'] }}</div>
                                            <div style="color: #64748b; font-size: 0.6875rem; font-family: monospace;">Ref: {{ $bill['vendor_reference'] }}</div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #334155; font-weight: 600;">
                                            {{ $bill['vendor_name'] }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #475569;">
                                            {{ $bill['issue_date'] }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #475569;">
                                            {{ $bill['due_date'] }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right; color: #64748b;">
                                            ₹{{ number_format($bill['grand_total'], 2) }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <div style="font-weight: 800; font-size: 0.8125rem; color: #7c3aed;">
                                                ₹{{ number_format($bill['balance_due'], 2) }}
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: center;">
                                            <span style="display: inline-block; background: #faf5ff; color: #7e22ce; font-size: 0.6875rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; border: 1px solid #e9d5ff;">
                                                {{ ucfirst($bill['status']) }}
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <button 
                                                type="button" 
                                                wire:click="mountAction('recordBillPayment', { bill_id: {{ $bill['id'] }}, amount: {{ $bill['balance_due'] }} })"
                                                style="font-size: 0.6875rem; font-weight: 700; color: #7c3aed; background: #faf5ff; border: 1px solid #e9d5ff; padding: 0.25rem 0.625rem; border-radius: 0.375rem; cursor: pointer;">
                                                Settle Bill
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        <!-- Tab 5: Owner Advances & Reserves Table -->
        @elseif($activeTab === 'owner_advances')
            @php $ownerAdvances = $this->getOwnerAdvances(); @endphp
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.875rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                @if($ownerAdvances->isEmpty())
                    <div style="padding: 3.5rem 2rem; text-align: center; color: #64748b;">
                        <div style="font-size: 1rem; font-weight: 700; color: #334155;">No properties found</div>
                        <div style="font-size: 0.8125rem; margin-top: 0.25rem;">Try adjusting your property filter.</div>
                    </div>
                @else
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.75rem;">
                            <thead>
                                <tr style="background: #f8fafc; color: #475569; font-weight: 700; border-bottom: 1px solid #e2e8f0; text-transform: uppercase; font-size: 0.6875rem; letter-spacing: 0.05em;">
                                    <th style="padding: 0.75rem 1rem;">Property Name</th>
                                    <th style="padding: 0.75rem 1rem;">Property Code</th>
                                    <th style="padding: 0.75rem 1rem;">Owner Name</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Total Advance Recovered</th>
                                    <th style="padding: 0.75rem 1rem;">Last Payout Date</th>
                                    <th style="padding: 0.75rem 1rem; text-align: right;">Last Disbursed Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($ownerAdvances as $prop)
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background-color 0.15s ease;">
                                        <td style="padding: 0.75rem 1rem; font-weight: 700; color: #0f172a;">
                                            {{ $prop['property_name'] }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; font-family: monospace; color: #64748b;">
                                            {{ $prop['property_code'] }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #334155; font-weight: 600;">
                                            {{ $prop['owner_name'] }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <div style="font-weight: 800; font-size: 0.8125rem; color: #d97706;">
                                                ₹{{ number_format($prop['total_offset_recovered'], 2) }}
                                            </div>
                                        </td>
                                        <td style="padding: 0.75rem 1rem; color: #475569;">
                                            {{ $prop['last_payout_date'] }}
                                        </td>
                                        <td style="padding: 0.75rem 1rem; text-align: right;">
                                            <div style="font-weight: 700; color: #15803d;">
                                                ₹{{ number_format($prop['last_payout_amount'], 2) }}
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</x-filament-panels::page>
