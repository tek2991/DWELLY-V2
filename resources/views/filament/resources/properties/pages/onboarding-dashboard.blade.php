@php
    $record = $this->record ?? (method_exists($this, 'getRecord') ? $this->getRecord() : null); 
    if ($record) {
        $record->refresh();
    }
    $validationData = app(\App\Domain\Property\Services\PropertyOnboardingValidator::class)->validate($record);
    $progress = $validationData['progress'] ?? 0;
    $steps = $validationData['steps'] ?? [];
    $status = $record->onboardingProject?->status ?? 'Draft';

    $statusBg = match($status) {
        'Activated' => '#dcfce7',
        'Pending Review' => '#fef3c7',
        'Changes Requested' => '#fee2e2',
        default => '#f1f5f9',
    };
    $statusText = match($status) {
        'Activated' => '#15803d',
        'Pending Review' => '#b45309',
        'Changes Requested' => '#b91c1c',
        default => '#475569',
    };
    $statusBorder = match($status) {
        'Activated' => '#86efac',
        'Pending Review' => '#fde68a',
        'Changes Requested' => '#fca5a5',
        default => '#cbd5e1',
    };
@endphp

<x-filament-widgets::widget 
    x-on:refresh-onboarding-progress.window="$wire.$refresh()" 
    x-on:refresh-page.window="$wire.$refresh()"
    x-on:update-relation-manager-list.window="$wire.$refresh()"
    x-on:saved.window="$wire.$refresh()"
    x-on:close-modal.window="$wire.$refresh()"
>
    <x-filament::section>
        <x-slot name="heading">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 0.875rem;">
                    <span style="font-size: 1.125rem; font-weight: 700; color: #0f172a;" class="dark:text-white">🚀 Onboarding Completion Progress</span>
                    <span style="font-size: 1.375rem; font-weight: 900; color: {{ $progress === 100 ? '#10b981' : '#f59e0b' }};">{{ $progress }}%</span>
                    <span style="font-size: 0.75rem; font-weight: 700; padding: 3px 10px; border-radius: 9999px; background-color: {{ $statusBg }}; color: {{ $statusText }}; border: 1px solid {{ $statusBorder }};">
                        {{ $status }}
                    </span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    @if($status === 'Activated')
                        <x-filament::button color="success" icon="heroicon-o-check-badge" disabled size="sm">
                            Property Activated & Live
                        </x-filament::button>
                    @elseif($status === 'Pending Review')
                        @if($this->canUserReview())
                            {{ $this->activatePropertyAction }}
                            {{ $this->requestChangesAction }}
                        @else
                            <x-filament::button color="warning" icon="heroicon-o-clock" disabled size="sm">
                                Awaiting Review
                            </x-filament::button>
                        @endif
                    @else
                        {{ $this->submitForReviewAction }}
                    @endif
                </div>
            </div>
        </x-slot>

        @if($status === 'Pending Review')
            <div style="margin-bottom: 1.25rem; padding: 1rem 1.25rem; border-radius: 0.5rem; background-color: #fffbeb; border: 1px solid #fde68a; color: #92400e; display: flex; align-items: center; justify-content: space-between; box-shadow: 0 1px 3px rgba(217, 119, 6, 0.08);">
                <div style="display: flex; align-items: center; gap: 0.625rem;">
                    <x-heroicon-o-clock style="width: 1.375rem; height: 1.375rem; color: #d97706; flex-shrink: 0;" />
                    <div>
                        <strong style="font-size: 0.875rem; color: #92400e;">Submitted for Operations Review</strong>
                        @if($record->onboardingProject?->submitted_at)
                            <span style="font-size: 0.75rem; color: #b45309; margin-left: 4px;">({{ $record->onboardingProject->submitted_at->diffForHumans() }})</span>
                        @endif
                        <div style="font-size: 0.75rem; color: #b45309; margin-top: 2px;">All 100% setup requirements have been submitted. An Operations Manager review is required to activate.</div>
                    </div>
                </div>
            </div>
        @elseif($status === 'Changes Requested' && $record->onboardingProject?->review_notes)
            <div style="margin-bottom: 1.25rem; padding: 1rem 1.25rem; border-radius: 0.5rem; background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b; box-shadow: 0 1px 3px rgba(220, 38, 38, 0.08);">
                <div style="display: flex; align-items: flex-start; gap: 0.625rem;">
                    <x-heroicon-o-exclamation-triangle style="width: 1.375rem; height: 1.375rem; color: #dc2626; flex-shrink: 0; margin-top: 2px;" />
                    <div>
                        <strong style="font-size: 0.875rem; color: #991b1b;">Revisions Requested by Reviewer</strong>
                        <div style="font-size: 0.8125rem; margin-top: 4px; color: #7f1d1d; background: #ffffff; border: 1px solid #fca5a5; padding: 8px 12px; border-radius: 6px;">
                            {{ $record->onboardingProject->review_notes }}
                        </div>
                    </div>
                </div>
            </div>
        @elseif($status === 'Activated')
            <div style="margin-bottom: 1.25rem; padding: 0.875rem 1.25rem; border-radius: 0.5rem; background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; display: flex; align-items: center; gap: 0.625rem;">
                <x-heroicon-o-check-badge style="width: 1.375rem; height: 1.375rem; color: #16a34a; flex-shrink: 0;" />
                <span style="font-size: 0.875rem; font-weight: 600;">Onboarding Completed. Property is Vacant and Live for tenant assignment.</span>
            </div>
        @endif

        <!-- Progress Bar Container -->
        <div style="width: 100%; border-radius: 9999px; height: 0.875rem; margin-bottom: 1.5rem; overflow: hidden; background-color: #e2e8f0; border: 1px solid #cbd5e1;" class="dark:bg-gray-800 dark:border-gray-700">
            <div style="height: 100%; border-radius: 9999px; transition: width 400ms ease-in-out; width: {{ $progress }}%; background: {{ $progress === 100 ? 'linear-gradient(90deg, #10b981, #059669)' : 'linear-gradient(90deg, #f59e0b, #d97706)' }};"></div>
        </div>

        <!-- Checklist Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
            @foreach($steps as $key => $step)
                @php
                    $isSuccess = $step['is_valid'];
                    $bgColor = $isSuccess ? '#f0fdf4' : '#fff1f2';
                    $borderColor = $isSuccess ? '#bbf7d0' : '#fecdd3';
                    $textColor = $isSuccess ? '#15803d' : '#be123c';
                    $badgeBg = $isSuccess ? '#dcfce7' : '#ffe4e6';
                    $iconColor = $isSuccess ? '#16a34a' : '#e11d48';
                @endphp
                <div style="padding: 1rem 1.125rem; border-radius: 0.75rem; border: 1px solid {{ $borderColor }}; background-color: {{ $bgColor }}; display: flex; flex-direction: column; justify-content: space-between; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                    <div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <x-filament::icon 
                                    :icon="$step['is_valid'] ? 'heroicon-s-check-circle' : 'heroicon-s-x-circle'" 
                                    style="width: 1.25rem; height: 1.25rem; flex-shrink: 0; color: {{ $iconColor }};"
                                />
                                <h3 style="font-size: 0.9375rem; font-weight: 700; margin: 0; color: {{ $textColor }};">
                                    {{ $step['name'] }}
                                </h3>
                            </div>
                            <span style="font-size: 0.6875rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; background: {{ $badgeBg }}; color: {{ $textColor }};">
                                {{ $step['is_valid'] ? 'COMPLETED' : 'PENDING' }}
                            </span>
                        </div>
                        
                        @if(!$step['is_valid'])
                            <div style="margin-top: 0.5rem; font-size: 0.8125rem; color: #9f1239;">
                                <ul style="list-style-type: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 0.25rem;">
                                    @foreach($step['missing'] as $msg)
                                        <li style="display: flex; align-items: flex-start; gap: 4px;">
                                            <span style="color: #e11d48; font-weight: 700;">•</span>
                                            <span>{{ $msg }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @else
                            <div style="font-size: 0.75rem; color: #166534; margin-top: 0.25rem;">
                                ✓ All requirements validated.
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
