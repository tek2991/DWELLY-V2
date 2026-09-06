<x-filament-widgets::widget class="fi-wi-action-alerts" style="grid-column: 1 / -1;">
    <x-filament::section>
    <x-slot name="heading">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="display: flex; width: 0.625rem; height: 0.625rem; border-radius: 9999px; background-color: #ef4444;"></span>
            <span style="font-weight: 700; font-size: 0.9375rem; color: #0f172a;">Operational Action Center</span>
            <span style="font-size: 0.6875rem; font-weight: 600; color: #64748b; background: #f1f5f9; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                Real-Time Attention Required
            </span>
        </div>
    </x-slot>

    @php
        $alerts = $this->getAlertsData();
    @endphp

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem; margin-top: 0.375rem;">
        
        <!-- 1. Urgent Maintenance -->
        <a href="{{ $alerts['maintenance']['url'] }}" style="text-decoration: none; color: inherit; display: block;">
            <div style="background: #ffffff; border: 1px solid #fee2e2; border-radius: 0.625rem; padding: 0.875rem 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); transition: all 0.2s ease; border-left: 4px solid #ef4444; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.625rem; font-weight: 700; color: #991b1b; text-transform: uppercase; letter-spacing: 0.05em;">Pending Repairs</div>
                        <div style="font-size: 1.375rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">
                            {{ $alerts['maintenance']['count'] }}
                        </div>
                    </div>
                    <div style="width: 2rem; height: 2rem; border-radius: 0.5rem; background: #fef2f2; color: #dc2626; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-filament::icon icon="heroicon-m-wrench-screwdriver" style="width: 1.125rem; height: 1.125rem;" />
                    </div>
                </div>
                <div style="margin-top: 0.625rem; display: flex; align-items: center; justify-content: space-between; gap: 0.25rem;">
                    <span style="font-size: 0.625rem; font-weight: 700; color: {{ $alerts['maintenance']['urgent_count'] > 0 ? '#b91c1c' : '#059669' }}; background: {{ $alerts['maintenance']['urgent_count'] > 0 ? '#fee2e2' : '#ecfdf5' }}; padding: 0.125rem 0.375rem; border-radius: 9999px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $alerts['maintenance']['urgent_count'] }} Emergency / High
                    </span>
                    <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; flex-shrink: 0;">&rarr;</span>
                </div>
            </div>
        </a>

        <!-- 2. Pending Works & Tasks -->
        <a href="{{ $alerts['tasks']['url'] }}" style="text-decoration: none; color: inherit; display: block;">
            <div style="background: #ffffff; border: 1px solid #ffedd5; border-radius: 0.625rem; padding: 0.875rem 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); transition: all 0.2s ease; border-left: 4px solid #f97316; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.625rem; font-weight: 700; color: #9a3412; text-transform: uppercase; letter-spacing: 0.05em;">Works & Tasks</div>
                        <div style="font-size: 1.375rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">
                            {{ $alerts['tasks']['count'] }}
                        </div>
                    </div>
                    <div style="width: 2rem; height: 2rem; border-radius: 0.5rem; background: #fff7ed; color: #ea580c; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-filament::icon icon="heroicon-m-clipboard-document-list" style="width: 1.125rem; height: 1.125rem;" />
                    </div>
                </div>
                <div style="margin-top: 0.625rem; display: flex; align-items: center; justify-content: space-between; gap: 0.25rem;">
                    <span style="font-size: 0.625rem; font-weight: 700; color: {{ $alerts['tasks']['overdue_count'] > 0 ? '#c2410c' : '#64748b' }}; background: {{ $alerts['tasks']['overdue_count'] > 0 ? '#ffedd5' : '#f1f5f9' }}; padding: 0.125rem 0.375rem; border-radius: 9999px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $alerts['tasks']['overdue_count'] }} Overdue SLA
                    </span>
                    <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; flex-shrink: 0;">&rarr;</span>
                </div>
            </div>
        </a>

        <!-- 3. Audits Pending Review -->
        <a href="{{ $alerts['audits']['url'] }}" style="text-decoration: none; color: inherit; display: block;">
            <div style="background: #ffffff; border: 1px solid #f3e8ff; border-radius: 0.625rem; padding: 0.875rem 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); transition: all 0.2s ease; border-left: 4px solid #a855f7; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.625rem; font-weight: 700; color: #6b21a8; text-transform: uppercase; letter-spacing: 0.05em;">Audits Review</div>
                        <div style="font-size: 1.375rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">
                            {{ $alerts['audits']['count'] }}
                        </div>
                    </div>
                    <div style="width: 2rem; height: 2rem; border-radius: 0.5rem; background: #faf5ff; color: #9333ea; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-filament::icon icon="heroicon-m-clipboard-document-check" style="width: 1.125rem; height: 1.125rem;" />
                    </div>
                </div>
                <div style="margin-top: 0.625rem; display: flex; align-items: center; justify-content: space-between; gap: 0.25rem;">
                    <span style="font-size: 0.625rem; font-weight: 700; color: {{ $alerts['audits']['count'] > 0 ? '#7e22ce' : '#64748b' }}; background: {{ $alerts['audits']['count'] > 0 ? '#f3e8ff' : '#f1f5f9' }}; padding: 0.125rem 0.375rem; border-radius: 9999px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $alerts['audits']['count'] > 0 ? 'Pending Review' : 'All Clear' }}
                    </span>
                    <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; flex-shrink: 0;">&rarr;</span>
                </div>
            </div>
        </a>

        <!-- 4. Rent Collections Pending -->
        <a href="{{ $alerts['rent']['url'] }}" style="text-decoration: none; color: inherit; display: block;">
            <div style="background: #ffffff; border: 1px solid #dcfce7; border-radius: 0.625rem; padding: 0.875rem 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); transition: all 0.2s ease; border-left: 4px solid #22c55e; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.625rem; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.05em;">Rent Due</div>
                        <div style="font-size: 1.375rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">
                            ₹{{ number_format($alerts['rent']['total'], 0) }}
                        </div>
                    </div>
                    <div style="width: 2rem; height: 2rem; border-radius: 0.5rem; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-filament::icon icon="heroicon-m-banknotes" style="width: 1.125rem; height: 1.125rem;" />
                    </div>
                </div>
                <div style="margin-top: 0.625rem; display: flex; align-items: center; justify-content: space-between; gap: 0.25rem;">
                    <span style="font-size: 0.625rem; font-weight: 700; color: {{ $alerts['rent']['count'] > 0 ? '#15803d' : '#64748b' }}; background: {{ $alerts['rent']['count'] > 0 ? '#dcfce7' : '#f1f5f9' }}; padding: 0.125rem 0.375rem; border-radius: 9999px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $alerts['rent']['count'] }} Invoices Due
                    </span>
                    <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; flex-shrink: 0;">&rarr;</span>
                </div>
            </div>
        </a>

        <!-- 5. Owner Payouts Pending -->
        <a href="{{ $alerts['payouts']['url'] }}" style="text-decoration: none; color: inherit; display: block;">
            <div style="background: #ffffff; border: 1px solid #dbeafe; border-radius: 0.625rem; padding: 0.875rem 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); transition: all 0.2s ease; border-left: 4px solid #3b82f6; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.625rem; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.05em;">Owner Payouts</div>
                        <div style="font-size: 1.375rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">
                            ₹{{ number_format($alerts['payouts']['total'], 0) }}
                        </div>
                    </div>
                    <div style="width: 2rem; height: 2rem; border-radius: 0.5rem; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-filament::icon icon="heroicon-m-arrow-up-tray" style="width: 1.125rem; height: 1.125rem;" />
                    </div>
                </div>
                <div style="margin-top: 0.625rem; display: flex; align-items: center; justify-content: space-between; gap: 0.25rem;">
                    <span style="font-size: 0.625rem; font-weight: 700; color: {{ $alerts['payouts']['count'] > 0 ? '#1d4ed8' : '#64748b' }}; background: {{ $alerts['payouts']['count'] > 0 ? '#dbeafe' : '#f1f5f9' }}; padding: 0.125rem 0.375rem; border-radius: 9999px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $alerts['payouts']['count'] }} Un-disbursed
                    </span>
                    <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; flex-shrink: 0;">&rarr;</span>
                </div>
            </div>
        </a>

        <!-- 6. Active Deboardings -->
        <a href="{{ $alerts['deboardings']['url'] }}" style="text-decoration: none; color: inherit; display: block;">
            <div style="background: #ffffff; border: 1px solid #ffe4e6; border-radius: 0.625rem; padding: 0.875rem 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); transition: all 0.2s ease; border-left: 4px solid #f43f5e; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.625rem; font-weight: 700; color: #9f1239; text-transform: uppercase; letter-spacing: 0.05em;">Deboardings</div>
                        <div style="font-size: 1.375rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">
                            {{ $alerts['deboardings']['count'] }}
                        </div>
                    </div>
                    <div style="width: 2rem; height: 2rem; border-radius: 0.5rem; background: #fff1f2; color: #e11d48; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-filament::icon icon="heroicon-m-arrow-left-on-rectangle" style="width: 1.125rem; height: 1.125rem;" />
                    </div>
                </div>
                <div style="margin-top: 0.625rem; display: flex; align-items: center; justify-content: space-between; gap: 0.25rem;">
                    <span style="font-size: 0.625rem; font-weight: 700; color: {{ $alerts['deboardings']['count'] > 0 ? '#be123c' : '#64748b' }}; background: {{ $alerts['deboardings']['count'] > 0 ? '#ffe4e6' : '#f1f5f9' }}; padding: 0.125rem 0.375rem; border-radius: 9999px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        In Progress
                    </span>
                    <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; flex-shrink: 0;">&rarr;</span>
                </div>
            </div>
        </a>

        <!-- 7. Expiring Leases (< 60 Days) -->
        <a href="{{ $alerts['expiring_leases']['url'] }}" style="text-decoration: none; color: inherit; display: block;">
            <div style="background: #ffffff; border: 1px solid #ede9fe; border-radius: 0.625rem; padding: 0.875rem 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.03); transition: all 0.2s ease; border-left: 4px solid #8b5cf6; height: 100%; display: flex; flex-direction: column; justify-content: space-between;">
                <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                    <div>
                        <div style="font-size: 0.625rem; font-weight: 700; color: #6d28d9; text-transform: uppercase; letter-spacing: 0.05em;">Expiring Leases</div>
                        <div style="font-size: 1.375rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">
                            {{ $alerts['expiring_leases']['count'] }}
                        </div>
                    </div>
                    <div style="width: 2rem; height: 2rem; border-radius: 0.5rem; background: #f5f3ff; color: #7c3aed; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                        <x-filament::icon icon="heroicon-m-clock" style="width: 1.125rem; height: 1.125rem;" />
                    </div>
                </div>
                <div style="margin-top: 0.625rem; display: flex; align-items: center; justify-content: space-between; gap: 0.25rem;">
                    <span style="font-size: 0.625rem; font-weight: 700; color: {{ $alerts['expiring_leases']['count'] > 0 ? '#6d28d9' : '#64748b' }}; background: {{ $alerts['expiring_leases']['count'] > 0 ? '#ede9fe' : '#f1f5f9' }}; padding: 0.125rem 0.375rem; border-radius: 9999px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                        {{ $alerts['expiring_leases']['count'] > 0 ? '₹'.number_format($alerts['expiring_leases']['total'], 0).'/mo at risk' : 'Next 60 Days' }}
                    </span>
                    <span style="font-size: 0.75rem; color: #94a3b8; font-weight: 700; flex-shrink: 0;">&rarr;</span>
                </div>
            </div>
        </a>

    </div>
</x-filament::section>
</x-filament-widgets::widget>
