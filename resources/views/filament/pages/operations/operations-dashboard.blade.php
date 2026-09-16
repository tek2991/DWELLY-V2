<x-filament-panels::page>
    @php
        $tabCounts = $this->getTabCounts();
    @endphp

    <div style="display: flex; flex-direction: column; gap: 1.25rem; width: 100%;">
        
        <!-- Tab Navigation Bar -->
        <div style="background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.3125rem; display: flex; gap: 0.3125rem; overflow-x: auto;">
            
            <!-- Tab 1: Portfolio Pipeline -->
            <button 
                type="button"
                wire:click="setTab('pipeline')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'pipeline' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <x-filament::icon icon="heroicon-m-building-office-2" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'pipeline' ? '#0284c7' : '#94a3b8' }};" />
                <span>Portfolio & Pipeline</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'pipeline' ? 'background: #e0f2fe; color: #0284c7;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $tabCounts['pipeline'] }}
                </span>
            </button>

            <!-- Tab 2: Maintenance & Works -->
            <button 
                type="button"
                wire:click="setTab('maintenance')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'maintenance' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <x-filament::icon icon="heroicon-m-wrench-screwdriver" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'maintenance' ? '#ef4444' : '#94a3b8' }};" />
                <span>Maintenance & Works</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'maintenance' ? 'background: #fee2e2; color: #dc2626;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $tabCounts['maintenance'] }}
                </span>
            </button>

            <!-- Tab 3: Audits & Quality -->
            <button 
                type="button"
                wire:click="setTab('audits')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'audits' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <x-filament::icon icon="heroicon-m-clipboard-document-check" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'audits' ? '#a855f7' : '#94a3b8' }};" />
                <span>Audits & Quality</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'audits' ? 'background: #f3e8ff; color: #7e22ce;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $tabCounts['audits'] }}
                </span>
            </button>

            <!-- Tab 4: Move-Ins & Deboardings -->
            <button 
                type="button"
                wire:click="setTab('moveins_moveouts')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'moveins_moveouts' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <x-filament::icon icon="heroicon-m-arrows-right-left" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'moveins_moveouts' ? '#10b981' : '#94a3b8' }};" />
                <span>Move-Ins & Deboardings</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'moveins_moveouts' ? 'background: #ecfdf5; color: #059669;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $tabCounts['moveins_moveouts'] }}
                </span>
            </button>

            <!-- Tab 5: Renewals & Expirations -->
            <button 
                type="button"
                wire:click="setTab('renewals')"
                style="display: flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: none; white-space: nowrap; transition: all 0.15s ease; {{ $activeTab === 'renewals' ? 'background: #ffffff; color: #0f172a; box-shadow: 0 1px 3px rgba(0,0,0,0.06); font-weight: 700;' : 'background: transparent; color: #64748b;' }}">
                <x-filament::icon icon="heroicon-m-clock" style="width: 1.125rem; height: 1.125rem; color: {{ $activeTab === 'renewals' ? '#8b5cf6' : '#94a3b8' }};" />
                <span>Renewals & Expirations</span>
                <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.1rem 0.4375rem; border-radius: 9999px; {{ $activeTab === 'renewals' ? 'background: #ede9fe; color: #7c3aed;' : 'background: #e2e8f0; color: #64748b;' }}">
                    {{ $tabCounts['renewals'] }}
                </span>
            </button>

        </div>

        <!-- ================= TAB 1: PIPELINE ================= -->
        @if ($activeTab === 'pipeline')
            @php
                $pipeline = $this->getPipelineData();
            @endphp

            <!-- KPI Cards Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.875rem;">
                
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Occupancy Rate</div>
                    <div style="font-size: 1.625rem; font-weight: 800; color: #059669; margin-top: 0.25rem;">{{ $pipeline['occupancy_rate'] }}%</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">{{ $pipeline['occupied'] }} of {{ $pipeline['occupied'] + $pipeline['vacant'] + $pipeline['maintenance'] }} active units</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Ready to Lease (Vacant)</div>
                    <div style="font-size: 1.625rem; font-weight: 800; color: #0284c7; margin-top: 0.25rem;">{{ $pipeline['vacant'] }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Prepared & showing ready</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">In Onboarding</div>
                    <div style="font-size: 1.625rem; font-weight: 800; color: #d97706; margin-top: 0.25rem;">{{ $pipeline['onboarding'] }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Awaiting audit & setup</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Under Turn / Repairs</div>
                    <div style="font-size: 1.625rem; font-weight: 800; color: #dc2626; margin-top: 0.25rem;">{{ $pipeline['maintenance'] }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Vacated / turnover prep</div>
                </div>

            </div>

            <!-- Two Columns: Onboarding Queue & Vacant Showcase -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.25rem; margin-top: 0.5rem;">
                
                <!-- Onboarding Queue -->
                <x-filament::section>
                    <x-slot name="heading">
                        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                            <span style="font-weight: 700; font-size: 0.9375rem;">Onboarding Properties Pending Activation</span>
                            <a href="{{ \App\Filament\Pages\Properties\OnboardingQueue::getUrl() }}" style="font-size: 0.75rem; color: #0284c7; text-decoration: none; font-weight: 600;">View All &rarr;</a>
                        </div>
                    </x-slot>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                            <thead>
                                <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                    <th style="padding: 0.625rem 0.5rem;">Code</th>
                                    <th style="padding: 0.625rem 0.5rem;">Property / Address</th>
                                    <th style="padding: 0.625rem 0.5rem;">Owner</th>
                                    <th style="padding: 0.625rem 0.5rem; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pipeline['onboarding_properties'] as $prop)
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #0f172a;">{{ $prop->code ?? 'PROP-' . $prop->id }}</td>
                                        <td style="padding: 0.625rem 0.5rem;">
                                            <div style="font-weight: 600; color: #1e293b;">{{ $prop->building_name ?? $prop->name ?? 'Property' }}</div>
                                            <div style="font-size: 0.6875rem; color: #64748b;">{{ $prop->localityRef?->name ?? $prop->city ?? 'N/A' }}</div>
                                        </td>
                                        <td style="padding: 0.625rem 0.5rem; color: #475569;">{{ $prop->owner?->display_name ?? 'Pending' }}</td>
                                        <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                            <a href="{{ \App\Filament\Resources\Properties\PropertyResource::getUrl('onboarding', ['record' => $prop]) }}" style="font-weight: 600; color: #0284c7; text-decoration: none;">Manage</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" style="padding: 1.5rem; text-align: center; color: #64748b;">No properties currently stuck in onboarding.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>

                <!-- Vacant Properties Ready for Showings -->
                <x-filament::section>
                    <x-slot name="heading">
                        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                            <span style="font-weight: 700; font-size: 0.9375rem;">Vacant Properties Ready to Lease</span>
                            <span style="font-size: 0.6875rem; font-weight: 700; background: #ecfdf5; color: #059669; padding: 0.125rem 0.5rem; border-radius: 9999px;">Ready</span>
                        </div>
                    </x-slot>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                            <thead>
                                <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                    <th style="padding: 0.625rem 0.5rem;">Code</th>
                                    <th style="padding: 0.625rem 0.5rem;">Property / City</th>
                                    <th style="padding: 0.625rem 0.5rem;">Target Rent</th>
                                    <th style="padding: 0.625rem 0.5rem; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($pipeline['vacant_properties'] as $prop)
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #0f172a;">{{ $prop->code ?? 'PROP-' . $prop->id }}</td>
                                        <td style="padding: 0.625rem 0.5rem;">
                                            <div style="font-weight: 600; color: #1e293b;">{{ $prop->building_name ?? $prop->name ?? 'Property' }}</div>
                                            <div style="font-size: 0.6875rem; color: #64748b;">{{ $prop->localityRef?->name ?? $prop->city ?? 'N/A' }}</div>
                                        </td>
                                        <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #0f172a;">
                                            ₹{{ number_format($prop->financialTerms->first()?->target_rent ?? 0) }}
                                        </td>
                                        <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                            <a href="{{ \App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $prop]) }}" style="font-weight: 600; color: #0284c7; text-decoration: none;">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" style="padding: 1.5rem; text-align: center; color: #64748b;">No vacant properties currently waiting.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>

            </div>

        <!-- ================= TAB 2: MAINTENANCE & WORKS ================= -->
        @elseif ($activeTab === 'maintenance')
            @php
                $maint = $this->getMaintenanceData();
            @endphp

            <!-- Priority Matrix -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.875rem;">
                <div style="background: #fff1f2; border: 1px solid #fecdd3; border-radius: 0.75rem; padding: 1rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #be123c; text-transform: uppercase;">P1 Emergency</div>
                    <div style="font-size: 1.625rem; font-weight: 800; color: #9f1239; margin-top: 0.25rem;">{{ $maint['p1_emergency'] }}</div>
                    <div style="font-size: 0.75rem; color: #e11d48; margin-top: 0.25rem;">Immediate response</div>
                </div>

                <div style="background: #fff7ed; border: 1px solid #fed7aa; border-radius: 0.75rem; padding: 1rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #c2410c; text-transform: uppercase;">P2 High Priority</div>
                    <div style="font-size: 1.625rem; font-weight: 800; color: #9a3412; margin-top: 0.25rem;">{{ $maint['p2_high'] }}</div>
                    <div style="font-size: 0.75rem; color: #ea580c; margin-top: 0.25rem;">Within 24 hours</div>
                </div>

                <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.75rem; padding: 1rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #1d4ed8; text-transform: uppercase;">P3 Medium Priority</div>
                    <div style="font-size: 1.625rem; font-weight: 800; color: #1e40af; margin-top: 0.25rem;">{{ $maint['p3_medium'] }}</div>
                    <div style="font-size: 0.75rem; color: #2563eb; margin-top: 0.25rem;">Within 48 hours</div>
                </div>

                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #475569; text-transform: uppercase;">P4 Routine</div>
                    <div style="font-size: 1.625rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">{{ $maint['p4_low'] }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Scheduled work</div>
                </div>
            </div>

            <!-- Active Tickets Table -->
            <x-filament::section>
                <x-slot name="heading">
                    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        <span style="font-weight: 700; font-size: 0.9375rem;">Active Maintenance Requests & Work Orders</span>
                        <a href="{{ \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('index') }}" style="font-size: 0.75rem; color: #0284c7; text-decoration: none; font-weight: 600;">View All Tickets &rarr;</a>
                    </div>
                </x-slot>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                <th style="padding: 0.625rem 0.5rem;">Ticket #</th>
                                <th style="padding: 0.625rem 0.5rem;">Issue Title</th>
                                <th style="padding: 0.625rem 0.5rem;">Property</th>
                                <th style="padding: 0.625rem 0.5rem;">Priority</th>
                                <th style="padding: 0.625rem 0.5rem;">Status</th>
                                <th style="padding: 0.625rem 0.5rem; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($maint['tickets'] as $ticket)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #0f172a;">{{ $ticket->ticket_number }}</td>
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 600; color: #1e293b;">{{ $ticket->title }}</td>
                                    <td style="padding: 0.625rem 0.5rem; color: #475569;">{{ $ticket->property?->building_name ?? 'N/A' }}</td>
                                    <td style="padding: 0.625rem 0.5rem;">
                                        <span style="font-size: 0.6875rem; font-weight: 700; padding: 0.125rem 0.5rem; border-radius: 9999px; {{ $ticket->priority->value === 'emergency' ? 'background: #fee2e2; color: #dc2626;' : ($ticket->priority->value === 'high' ? 'background: #ffedd5; color: #ea580c;' : 'background: #f1f5f9; color: #475569;') }}">
                                            {{ strtoupper($ticket->priority->value) }}
                                        </span>
                                    </td>
                                    <td style="padding: 0.625rem 0.5rem;">
                                        <span style="font-size: 0.6875rem; font-weight: 600; color: #0284c7; background: #e0f2fe; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                                            {{ $ticket->status->getLabel() }}
                                        </span>
                                    </td>
                                    <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                        <a href="{{ \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $ticket]) }}" style="font-weight: 600; color: #0284c7; text-decoration: none;">Manage</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding: 1.5rem; text-align: center; color: #64748b;">No active maintenance tickets found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

        <!-- ================= TAB 3: AUDITS & QUALITY ================= -->
        @elseif ($activeTab === 'audits')
            @php
                $audits = $this->getAuditsData();
            @endphp

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.875rem;">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Pending Review & Sign-off</div>
                    <div style="font-size: 1.625rem; font-weight: 800; color: #7e22ce; margin-top: 0.25rem;">{{ $audits['pending_review_count'] }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Field submissions awaiting manager approval</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Conducted This Month</div>
                    <div style="font-size: 1.625rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">{{ $audits['total_this_month'] }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Move-in, move-out & routine checks</div>
                </div>

                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem;">
                    <div style="font-size: 0.6875rem; font-weight: 700; color: #64748b; text-transform: uppercase;">Approved & Signed Off</div>
                    <div style="font-size: 1.625rem; font-weight: 800; color: #059669; margin-top: 0.25rem;">{{ $audits['completed_this_month'] }}</div>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">Fully processed audits</div>
                </div>
            </div>

            <!-- Review Queue Table -->
            <x-filament::section>
                <x-slot name="heading">
                    <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                        <span style="font-weight: 700; font-size: 0.9375rem;">Audits Requiring Manager Review</span>
                        <a href="{{ \App\Filament\Pages\Operations\InspectionQueue::getUrl() }}" style="font-size: 0.75rem; color: #0284c7; text-decoration: none; font-weight: 600;">Inspection Queue &rarr;</a>
                    </div>
                </x-slot>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                <th style="padding: 0.625rem 0.5rem;">Audit #</th>
                                <th style="padding: 0.625rem 0.5rem;">Property</th>
                                <th style="padding: 0.625rem 0.5rem;">Audit Type</th>
                                <th style="padding: 0.625rem 0.5rem;">Inspector</th>
                                <th style="padding: 0.625rem 0.5rem;">Status</th>
                                <th style="padding: 0.625rem 0.5rem; text-align: right;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($audits['pending_audits'] as $audit)
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #0f172a;">{{ $audit->audit_number }}</td>
                                    <td style="padding: 0.625rem 0.5rem; font-weight: 600; color: #1e293b;">{{ $audit->property?->building_name ?? 'N/A' }}</td>
                                    <td style="padding: 0.625rem 0.5rem;">
                                        <span style="font-size: 0.6875rem; font-weight: 700; background: #e0f2fe; color: #0369a1; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                                            {{ $audit->audit_type }}
                                        </span>
                                    </td>
                                    <td style="padding: 0.625rem 0.5rem; color: #475569;">{{ $audit->inspector?->name ?? 'Unassigned' }}</td>
                                    <td style="padding: 0.625rem 0.5rem;">
                                        <span style="font-size: 0.6875rem; font-weight: 600; color: #7e22ce; background: #f3e8ff; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                                            {{ $audit->status->value }}
                                        </span>
                                    </td>
                                    <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                        <a href="{{ \App\Filament\Resources\Operations\AuditResource::getUrl('review', ['record' => $audit]) }}" style="font-weight: 700; color: #7e22ce; text-decoration: none;">Review & Approve</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding: 1.5rem; text-align: center; color: #64748b;">No audits currently awaiting review.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-filament::section>

        <!-- ================= TAB 4: MOVE-INS & DEBOARDINGS ================= -->
        @elseif ($activeTab === 'moveins_moveouts')
            @php
                $lifecycle = $this->getMoveInsMoveOutsData();
            @endphp

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 1.25rem;">
                
                <!-- Upcoming Move-Ins -->
                <x-filament::section>
                    <x-slot name="heading">
                        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                            <span style="font-weight: 700; font-size: 0.9375rem;">Upcoming Tenant Move-Ins</span>
                            <span style="font-size: 0.6875rem; font-weight: 700; background: #ecfdf5; color: #059669; padding: 0.125rem 0.5rem; border-radius: 9999px;">Next 30 Days</span>
                        </div>
                    </x-slot>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                            <thead>
                                <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                    <th style="padding: 0.625rem 0.5rem;">Agreement</th>
                                    <th style="padding: 0.625rem 0.5rem;">Property</th>
                                    <th style="padding: 0.625rem 0.5rem;">Start Date</th>
                                    <th style="padding: 0.625rem 0.5rem;">Deposit</th>
                                    <th style="padding: 0.625rem 0.5rem; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lifecycle['upcoming_move_ins'] as $ag)
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #0f172a;">{{ $ag->code }}</td>
                                        <td style="padding: 0.625rem 0.5rem; font-weight: 600; color: #1e293b;">{{ $ag->property?->building_name ?? 'Property' }}</td>
                                        <td style="padding: 0.625rem 0.5rem; color: #059669; font-weight: 600;">
                                            {{ $ag->start_date ? \Carbon\Carbon::parse($ag->start_date)->format('d M Y') : 'Pending' }}
                                        </td>
                                        <td style="padding: 0.625rem 0.5rem; color: #475569;">₹{{ number_format($ag->security_deposit ?? 0) }}</td>
                                        <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                            <a href="{{ \App\Filament\Resources\TenancyAgreements\TenancyAgreementResource::getUrl('edit', ['record' => $ag]) }}" style="font-weight: 600; color: #0284c7; text-decoration: none;">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="padding: 1.5rem; text-align: center; color: #64748b;">No upcoming move-ins in the next 30 days.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>

                <!-- Active Deboardings -->
                <x-filament::section>
                    <x-slot name="heading">
                        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                            <span style="font-weight: 700; font-size: 0.9375rem;">Active Deboardings / Move-Outs</span>
                            <a href="{{ \App\Filament\Resources\Operations\TenantDeboardingResource::getUrl('index') }}" style="font-size: 0.75rem; color: #e11d48; text-decoration: none; font-weight: 600;">View All &rarr;</a>
                        </div>
                    </x-slot>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                            <thead>
                                <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                    <th style="padding: 0.625rem 0.5rem;">Deboarding #</th>
                                    <th style="padding: 0.625rem 0.5rem;">Property</th>
                                    <th style="padding: 0.625rem 0.5rem;">Vacating Date</th>
                                    <th style="padding: 0.625rem 0.5rem;">Status</th>
                                    <th style="padding: 0.625rem 0.5rem; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($lifecycle['active_deboardings'] as $deb)
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #0f172a;">{{ $deb->code }}</td>
                                        <td style="padding: 0.625rem 0.5rem; font-weight: 600; color: #1e293b;">{{ $deb->property?->building_name ?? 'Property' }}</td>
                                        <td style="padding: 0.625rem 0.5rem; color: #e11d48; font-weight: 600;">
                                            {{ $deb->target_vacating_date ? \Carbon\Carbon::parse($deb->target_vacating_date)->format('d M Y') : 'N/A' }}
                                        </td>
                                        <td style="padding: 0.625rem 0.5rem;">
                                            <span style="font-size: 0.6875rem; font-weight: 700; background: #ffe4e6; color: #be123c; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                                                {{ $deb->status->getLabel() }}
                                            </span>
                                        </td>
                                        <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                            <a href="{{ \App\Filament\Resources\Operations\TenantDeboardingResource::getUrl('edit', ['record' => $deb]) }}" style="font-weight: 600; color: #e11d48; text-decoration: none;">Track</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" style="padding: 1.5rem; text-align: center; color: #64748b;">No active deboardings in progress.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>

            </div>

        @endif

        <!-- ================= TAB 5: RENEWALS & EXPIRATIONS ================= -->
        @if ($activeTab === 'renewals')
            @php
                $renewals = $this->getRenewalsData();
            @endphp

            <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                
                <!-- Renewals Horizon & Rent at Risk Metrics -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.875rem;">
                    
                    <!-- 1. Critical < 30 Days -->
                    <x-filament::section style="border-left: 4px solid #ef4444;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                            <div>
                                <div style="font-size: 0.6875rem; font-weight: 700; color: #991b1b; text-transform: uppercase;">Critical (&lt; 30 Days)</div>
                                <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">{{ $renewals['critical_count'] }}</div>
                                <div style="font-size: 0.6875rem; color: #ef4444; font-weight: 600; margin-top: 0.25rem;">Urgent notice / action needed</div>
                            </div>
                            <div style="width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; background: #fef2f2; color: #dc2626; display: flex; align-items: center; justify-content: center;">
                                <x-filament::icon icon="heroicon-m-exclamation-triangle" style="width: 1.25rem; height: 1.25rem;" />
                            </div>
                        </div>
                    </x-filament::section>

                    <!-- 2. Upcoming 31-60 Days -->
                    <x-filament::section style="border-left: 4px solid #f59e0b;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                            <div>
                                <div style="font-size: 0.6875rem; font-weight: 700; color: #92400e; text-transform: uppercase;">Upcoming (31–60 Days)</div>
                                <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">{{ $renewals['upcoming_count'] }}</div>
                                <div style="font-size: 0.6875rem; color: #d97706; font-weight: 600; margin-top: 0.25rem;">Renewal negotiation window</div>
                            </div>
                            <div style="width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; background: #fffbeb; color: #d97706; display: flex; align-items: center; justify-content: center;">
                                <x-filament::icon icon="heroicon-m-chat-bubble-left-right" style="width: 1.25rem; height: 1.25rem;" />
                            </div>
                        </div>
                    </x-filament::section>

                    <!-- 3. Pipeline 61-90 Days -->
                    <x-filament::section style="border-left: 4px solid #3b82f6;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                            <div>
                                <div style="font-size: 0.6875rem; font-weight: 700; color: #1e40af; text-transform: uppercase;">Pipeline (61–90 Days)</div>
                                <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">{{ $renewals['pipeline_count'] }}</div>
                                <div style="font-size: 0.6875rem; color: #2563eb; font-weight: 600; margin-top: 0.25rem;">Early tenant check-in</div>
                            </div>
                            <div style="width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center;">
                                <x-filament::icon icon="heroicon-m-calendar" style="width: 1.25rem; height: 1.25rem;" />
                            </div>
                        </div>
                    </x-filament::section>

                    <!-- 4. Monthly Rent at Risk -->
                    <x-filament::section style="border-left: 4px solid #8b5cf6;">
                        <div style="display: flex; align-items: flex-start; justify-content: space-between;">
                            <div>
                                <div style="font-size: 0.6875rem; font-weight: 700; color: #6d28d9; text-transform: uppercase;">Monthly Rent at Risk</div>
                                <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 0.25rem;">₹{{ number_format($renewals['rent_at_risk'], 0) }}</div>
                                <div style="font-size: 0.6875rem; color: #7c3aed; font-weight: 600; margin-top: 0.25rem;">Across {{ $renewals['total_expiring'] }} expiring leases</div>
                            </div>
                            <div style="width: 2.25rem; height: 2.25rem; border-radius: 0.5rem; background: #f5f3ff; color: #7c3aed; display: flex; align-items: center; justify-content: center;">
                                <x-filament::icon icon="heroicon-m-currency-rupee" style="width: 1.25rem; height: 1.25rem;" />
                            </div>
                        </div>
                    </x-filament::section>

                </div>

                <!-- Expiring Leases & Renewal Pipeline Table -->
                <x-filament::section>
                    <x-slot name="heading">
                        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-weight: 700; font-size: 0.9375rem;">Expiring Leases & Renewal Pipeline (&lt; 90 Days)</span>
                                <span style="font-size: 0.6875rem; font-weight: 700; background: #ede9fe; color: #7c3aed; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                                    {{ $renewals['total_expiring'] }} Active Leases
                                </span>
                            </div>
                            <a href="{{ \App\Filament\Resources\TenancyAgreements\TenancyAgreementResource::getUrl('index') }}" style="font-size: 0.75rem; color: #7c3aed; text-decoration: none; font-weight: 600;">View All Leases &rarr;</a>
                        </div>
                    </x-slot>

                    <div style="overflow-x: auto;">
                        <table style="width: 100%; text-align: left; border-collapse: collapse; font-size: 0.8125rem;">
                            <thead>
                                <tr style="border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                    <th style="padding: 0.625rem 0.5rem;">Property / Unit</th>
                                    <th style="padding: 0.625rem 0.5rem;">Primary Tenant</th>
                                    <th style="padding: 0.625rem 0.5rem;">Monthly Rent</th>
                                    <th style="padding: 0.625rem 0.5rem;">Expiry Date & Countdown</th>
                                    <th style="padding: 0.625rem 0.5rem;">Notice Period</th>
                                    <th style="padding: 0.625rem 0.5rem;">Status / Deboarding</th>
                                    <th style="padding: 0.625rem 0.5rem; text-align: right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($renewals['agreements'] as $agr)
                                    @php
                                        $tenant = $agr->tenants->first();
                                        $endDate = \Carbon\Carbon::parse($agr->end_date);
                                        $diffDays = (int) now()->diffInDays($endDate, false);
                                    @endphp
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 0.625rem 0.5rem;">
                                            <div style="font-weight: 700; color: #0f172a;">{{ $agr->property?->building_name ?? 'Property' }}</div>
                                            <div style="font-size: 0.6875rem; color: #64748b;">{{ $agr->property?->code ?? $agr->code }}</div>
                                        </td>
                                        <td style="padding: 0.625rem 0.5rem;">
                                            <div style="font-weight: 600; color: #1e293b;">{{ $tenant?->display_name ?? 'Tenant' }}</div>
                                            <div style="font-size: 0.6875rem; color: #64748b;">{{ $tenant?->phone_number ?? '-' }}</div>
                                        </td>
                                        <td style="padding: 0.625rem 0.5rem; font-weight: 700; color: #0f172a;">
                                            ₹{{ number_format($agr->rent_amount, 0) }}
                                        </td>
                                        <td style="padding: 0.625rem 0.5rem;">
                                            <div style="font-weight: 600; color: #1e293b;">{{ $endDate->format('d M Y') }}</div>
                                            @if ($diffDays < 0)
                                                <span style="font-size: 0.6875rem; font-weight: 700; background: #fee2e2; color: #b91c1c; padding: 0.1rem 0.375rem; border-radius: 9999px;">
                                                    Expired ({{ abs($diffDays) }}d ago)
                                                </span>
                                            @elseif ($diffDays <= 30)
                                                <span style="font-size: 0.6875rem; font-weight: 700; background: #fef2f2; color: #dc2626; padding: 0.1rem 0.375rem; border-radius: 9999px;">
                                                    {{ $diffDays }} days left
                                                </span>
                                            @elseif ($diffDays <= 60)
                                                <span style="font-size: 0.6875rem; font-weight: 700; background: #fef3c7; color: #d97706; padding: 0.1rem 0.375rem; border-radius: 9999px;">
                                                    {{ $diffDays }} days left
                                                </span>
                                            @else
                                                <span style="font-size: 0.6875rem; font-weight: 700; background: #eff6ff; color: #2563eb; padding: 0.1rem 0.375rem; border-radius: 9999px;">
                                                    {{ $diffDays }} days left
                                                </span>
                                            @endif
                                        </td>
                                        <td style="padding: 0.625rem 0.5rem; color: #64748b;">
                                            {{ $agr->notice_period_days ?? 30 }} days
                                        </td>
                                        <td style="padding: 0.625rem 0.5rem;">
                                            @if ($agr->deboarding)
                                                <span style="font-size: 0.6875rem; font-weight: 700; background: #ffe4e6; color: #be123c; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                                                    Deboarding: {{ $agr->deboarding->status?->getLabel() ?? 'Active' }}
                                                </span>
                                            @else
                                                <span style="font-size: 0.6875rem; font-weight: 600; background: #ecfdf5; color: #059669; padding: 0.125rem 0.5rem; border-radius: 9999px;">
                                                    Active Lease
                                                </span>
                                            @endif
                                        </td>
                                        <td style="padding: 0.625rem 0.5rem; text-align: right;">
                                            <div style="display: flex; align-items: center; justify-content: flex-end; gap: 0.5rem;">
                                                <a href="{{ \App\Filament\Resources\TenancyAgreements\TenancyAgreementResource::getUrl('edit', ['record' => $agr]) }}" 
                                                   style="font-size: 0.75rem; font-weight: 600; color: #2563eb; text-decoration: none; padding: 0.25rem 0.5rem; background: #eff6ff; border-radius: 0.375rem;">
                                                    View Lease
                                                </a>
                                                @if (! $agr->deboarding)
                                                    <a href="{{ \App\Filament\Resources\TenancyAgreements\TenancyAgreementResource::getUrl('deboard', ['record' => $agr]) }}" 
                                                       style="font-size: 0.75rem; font-weight: 600; color: #e11d48; text-decoration: none; padding: 0.25rem 0.5rem; background: #fff1f2; border-radius: 0.375rem;">
                                                        Deboard
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" style="padding: 1.5rem; text-align: center; color: #64748b;">
                                            No leases expiring within the next 90 days. All active tenancies are secured.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-filament::section>

            </div>

        @endif

    </div>
</x-filament-panels::page>
