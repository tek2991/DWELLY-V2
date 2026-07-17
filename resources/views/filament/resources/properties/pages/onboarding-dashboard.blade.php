@php
    // Get the record from the View component or Widget
    $record = $this->record ?? (method_exists($this, 'getRecord') ? $this->getRecord() : null); 
    $validationData = app(\App\Domain\Property\Services\PropertyOnboardingValidator::class)->validate($record);
    $progress = $validationData['progress'];
    $steps = $validationData['steps'];
@endphp

<x-filament-widgets::widget wire:poll.2s>
    <x-filament::section>
        <x-slot name="heading">
            <div style="display: flex; justify-content: space-between; align-items: center; width: 100%;">
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span>Onboarding Progress</span>
                    <span style="font-size: 1.5rem; font-weight: 900; color: {{ $progress === 100 ? '#10b981' : '#f59e0b' }};">
                        {{ $progress }}%
                    </span>
                </div>
                
                @if($record->onboardingProject?->status === 'Activated')
                    <x-filament::button color="success" icon="heroicon-o-check-badge" disabled>
                        Property Activated
                    </x-filament::button>
                @else
                    <x-filament::button 
                        wire:click="activateProperty"
                        wire:confirm="Are you sure you want to activate this property? It will be marked as Vacant and available for operations."
                        color="success" 
                        icon="heroicon-o-check-badge"
                        :disabled="$progress != 100"
                    >
                        Activate Property
                    </x-filament::button>
                @endif
            </div>
        </x-slot>

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
</x-filament-widgets::widget>
