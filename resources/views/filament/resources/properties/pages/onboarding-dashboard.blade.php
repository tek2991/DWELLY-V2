@php
    $record = $this->record ?? (method_exists($this, 'getRecord') ? $this->getRecord() : null); 
    if ($record) {
        $record->refresh();
    }
    $validationData = app(\App\Domain\Property\Services\PropertyOnboardingValidator::class)->validate($record);
    $progress = $validationData['progress'];
    $steps = $validationData['steps'];
    $status = $record->onboardingProject?->status ?? 'Draft';
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
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span>Onboarding Progress</span>
                    <span style="font-size: 1.5rem; font-weight: 900; color: {{ $progress === 100 ? '#10b981' : '#f59e0b' }};">{{ $progress }}%</span>
                    <span style="font-size: 0.875rem; font-weight: 600; padding: 0.25rem 0.75rem; border-radius: 9999px; background-color: {{ match($status) { 'Activated' => '#dcfce7', 'Pending Review' => '#fef3c7', 'Changes Requested' => '#fee2e2', default => '#f3f4f6' } }}; color: {{ match($status) { 'Activated' => '#15803d', 'Pending Review' => '#b45309', 'Changes Requested' => '#b91c1c', default => '#374151' } }};">
                        {{ $status }}
                    </span>
                </div>
                
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    @if($status === 'Activated')
                        <x-filament::button color="success" icon="heroicon-o-check-badge" disabled>
                            Property Activated
                        </x-filament::button>
                    @elseif($status === 'Pending Review')
                        @if($this->canUserReview())
                            {{ $this->activatePropertyAction }}
                            {{ $this->requestChangesAction }}
                        @endif
                    @else
                        {{ $this->submitForReviewAction }}
                    @endif
                </div>
            </div>
        </x-slot>

        @if($status === 'Pending Review')
            <div style="margin-bottom: 1.25rem; padding: 0.875rem 1rem; border-radius: 0.5rem; background-color: #fffbeb; border: 1px solid #fde68a; color: #92400e; display: flex; align-items: center; justify-content: space-between;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <x-heroicon-o-clock style="width: 1.25rem; height: 1.25rem; color: #d97706;" />
                    <span style="font-weight: 600; font-size: 0.875rem;">
                        Submitted for Operations Review
                        @if($record->onboardingProject?->submitted_at)
                            ({{ $record->onboardingProject->submitted_at->diffForHumans() }})
                        @endif
                    </span>
                </div>
                <span style="font-size: 0.75rem; color: #b45309;">Operations Manager review and activation required.</span>
            </div>
        @elseif($status === 'Changes Requested' && $record->onboardingProject?->review_notes)
            <div style="margin-bottom: 1.25rem; padding: 0.875rem 1rem; border-radius: 0.5rem; background-color: #fef2f2; border: 1px solid #fecaca; color: #991b1b;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
                    <x-heroicon-o-exclamation-triangle style="width: 1.25rem; height: 1.25rem; color: #dc2626;" />
                    <strong style="font-size: 0.875rem;">Revisions Requested by Reviewer</strong>
                </div>
                <p style="font-size: 0.875rem; margin: 0; padding-left: 1.75rem;">
                    {{ $record->onboardingProject->review_notes }}
                </p>
            </div>
        @endif

        <!-- Progress Bar -->
        <div style="width: 100%; border-radius: 9999px; height: 1rem; margin-bottom: 1.5rem; overflow: hidden; background-color: rgba(128, 128, 128, 0.2);">
            <div style="height: 100%; border-radius: 9999px; transition: all 500ms ease-out; width: {{ $progress }}%; background-color: {{ $progress === 100 ? '#10b981' : '#f59e0b' }};"></div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
            @foreach($steps as $key => $step)
                @php
                    $isSuccess = $step['is_valid'];
                    $bgColor = $isSuccess ? 'rgba(16, 185, 129, 0.05)' : 'rgba(239, 68, 68, 0.05)';
                    $borderColor = $isSuccess ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)';
                    $textColor = $isSuccess ? '#059669' : '#dc2626';
                    $iconColor = $isSuccess ? '#10b981' : '#ef4444';
                @endphp
                <div style="padding: 1rem; border-radius: 0.75rem; border: 1px solid {{ $borderColor }}; background-color: {{ $bgColor }};">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <x-filament::icon 
                            :icon="$step['is_valid'] ? 'heroicon-s-check-circle' : 'heroicon-s-x-circle'" 
                            style="width: 1.25rem; height: 1.25rem; flex-shrink: 0; color: {{ $iconColor }};"
                        />
                        <h3 style="font-size: 1rem; font-weight: 600; margin: 0; color: {{ $textColor }};">
                            {{ $step['name'] }}
                        </h3>
                    </div>
                    
                    @if(!$step['is_valid'])
                        <div style="margin-top: 0.75rem; padding-left: 1.75rem; font-size: 0.875rem; color: #dc2626;">
                            <ul style="list-style-type: disc; margin: 0; padding-left: 1rem; display: flex; flex-direction: column; gap: 0.25rem;">
                                @foreach($step['missing'] as $msg)
                                    <li>{{ $msg }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
