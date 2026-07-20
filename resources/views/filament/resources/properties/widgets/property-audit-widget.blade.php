<x-filament-widgets::widget>
    @php
        $latest = $this->getLatestAudit();
        $latestApproved = $this->getLatestApprovedAudit();
        $stats = $this->getAuditStats();
    @endphp
    
    <x-filament::section>
        <x-slot name="heading">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; min-height: 2rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; align-self: center;">
                    <x-filament::icon icon="heroicon-o-shield-check" class="h-5 w-5 text-primary-500" style="width: 1.25rem; height: 1.25rem; color: var(--primary-500);" />
                    <span style="line-height: 1;">Property Audits</span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 0.75rem; align-self: center; margin-top: -0.25rem; margin-bottom: -0.25rem;">
                    @if($latest && in_array($latest->status, [\App\Domain\Audit\Enums\AuditStatus::DRAFT, \App\Domain\Audit\Enums\AuditStatus::IN_PROGRESS, \App\Domain\Audit\Enums\AuditStatus::COMPLETED]))
                        @php
                            $actionLabel = match($latest->status) {
                                \App\Domain\Audit\Enums\AuditStatus::DRAFT => 'Continue Draft',
                                \App\Domain\Audit\Enums\AuditStatus::IN_PROGRESS => 'Continue Audit',
                                \App\Domain\Audit\Enums\AuditStatus::COMPLETED => 'Review Audit',
                                default => 'View Audit'
                            };
                        @endphp
                        <x-filament::button tag="a" color="info" size="sm" href="{{ \App\Filament\Resources\Operations\AuditResource::getUrl('edit', ['record' => $latest->id]) }}">
                            {{ $actionLabel }}
                        </x-filament::button>
                    @else
                        @php
                            $isDisabled = empty($this->record->code) || $this->record->onboardingProject?->status !== 'Activated';
                        @endphp
                        @if($isDisabled)
                            <span x-data="{}" x-tooltip="'Complete onboarding and generate property code first.'">
                                <x-filament::button tag="button" color="primary" size="sm" disabled>
                                    @if($stats['total'] === 0) Start Move-In Audit @else Start New Audit @endif
                                </x-filament::button>
                            </span>
                        @else
                            <x-filament::button tag="a" color="primary" size="sm" href="{{ \App\Filament\Resources\Operations\AuditResource::getUrl('create', ['property_id' => $this->record->id, 'audit_type' => $stats['total'] === 0 ? 'move_in' : null]) }}">
                                @if($stats['total'] === 0) Start Move-In Audit @else Start New Audit @endif
                            </x-filament::button>
                        @endif
                    @endif
                    
                    @if($stats['total'] > 0)
                        <x-filament::button tag="a" color="gray" outlined size="sm" href="#relation-manager-audits">
                            View All
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </x-slot>

        <x-slot name="description">
            Manage inspections and view historical condition reports.
        </x-slot>

        <!-- Safe 3-Column Layout using Inline Flex -->
        <div style="display: flex; flex-wrap: wrap; gap: 1.5rem; justify-content: space-between; align-items: stretch; width: 100%;">
            
            <!-- Current Audit -->
            <div style="flex: 1 1 250px; display: flex; flex-direction: column;">
                <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); margin-bottom: 0.75rem;">Current Audit</div>
                
                @if($latest && in_array($latest->status, [\App\Domain\Audit\Enums\AuditStatus::DRAFT, \App\Domain\Audit\Enums\AuditStatus::IN_PROGRESS, \App\Domain\Audit\Enums\AuditStatus::COMPLETED]))
                    <div>
                        <div style="margin-bottom: 0.5rem;">
                            <x-filament::badge :color="$latest->status->getColor()">
                                {{ $latest->status->getLabel() }}
                            </x-filament::badge>
                        </div>
                        <div style="font-size: 1rem; font-weight: 500;">{{ $latest->audit_type->getLabel() }}</div>
                    </div>
                @else
                    <div>
                        <div style="margin-bottom: 0.5rem;">
                            <x-filament::badge color="gray">
                                None
                            </x-filament::badge>
                        </div>
                        <div style="font-size: 1rem; font-weight: 500; color: var(--gray-500);">No Active Audit</div>
                    </div>
                @endif
            </div>

            <!-- Latest Approved -->
            <div style="flex: 1 1 250px; display: flex; flex-direction: column;">
                <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); margin-bottom: 0.75rem;">Latest Approved</div>
                
                @if($latestApproved)
                    <div>
                        <div style="margin-bottom: 0.5rem;">
                            <x-filament::badge color="success">
                                Approved
                            </x-filament::badge>
                        </div>
                        <div style="font-size: 1rem; font-weight: 500;">{{ $latestApproved->audit_type->getLabel() }}</div>
                        <div style="font-size: 0.875rem; color: var(--gray-500); margin-top: 0.25rem;">{{ $latestApproved->approved_at->format('j M Y') }}</div>
                    </div>
                @else
                    <div>
                        <div style="margin-bottom: 0.5rem;">
                            <x-filament::badge color="gray">
                                None
                            </x-filament::badge>
                        </div>
                        <div style="font-size: 1rem; font-weight: 500; color: var(--gray-500);">No Approved Audit</div>
                    </div>
                @endif
            </div>

            <!-- Audit History -->
            <div style="flex: 1 1 250px; display: flex; flex-direction: column;">
                <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--gray-500); margin-bottom: 0.75rem;">Audit History</div>
                
                <div>
                    <div style="font-size: 1.875rem; font-weight: 700; line-height: 1;">{{ $stats['total'] }}</div>
                    <div style="font-size: 0.875rem; font-weight: 500; color: var(--gray-500); margin-top: 0.5rem;">Total Audits</div>
                </div>
            </div>

        </div>
    </x-filament::section>
</x-filament-widgets::widget>
