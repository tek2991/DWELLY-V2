@php
    // Get the record from the View component or Widget
    $record = $this->getRecord ?? $this->record ?? $getRecord(); 
    $validationData = app(\App\Domain\Property\Services\PropertyOnboardingValidator::class)->validate($record);
    $progress = $validationData['progress'];
    $steps = $validationData['steps'];
@endphp

<div class="mb-8 p-6 bg-white dark:bg-gray-900 rounded-xl shadow-sm border border-gray-200 dark:border-gray-800">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-bold text-gray-900 dark:text-white">Onboarding Progress</h2>
        <span class="text-2xl font-black {{ $progress === 100 ? 'text-green-600' : 'text-primary-600' }}">
            {{ $progress }}%
        </span>
    </div>
    
    <!-- Progress Bar -->
    <div class="w-full bg-gray-200 dark:bg-gray-800 rounded-full h-4 mb-6 overflow-hidden">
        <div class="h-4 rounded-full transition-all duration-500 ease-out {{ $progress === 100 ? 'bg-green-500' : 'bg-primary-500' }}" style="width: {{ $progress }}%"></div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @foreach($steps as $key => $step)
            <div class="p-4 rounded-lg border {{ $step['is_valid'] ? 'border-green-200 bg-green-50 dark:border-green-900/50 dark:bg-green-900/20' : 'border-red-200 bg-red-50 dark:border-red-900/50 dark:bg-red-900/20' }}">
                <div class="flex items-center gap-2.5">
                    @if($step['is_valid'])
                        <x-heroicon-s-check-circle class="w-5 h-5 shrink-0 text-green-600 dark:text-green-400" style="width: 1.25rem; height: 1.25rem;" />
                    @else
                        <x-heroicon-s-x-circle class="w-5 h-5 shrink-0 text-red-600 dark:text-red-400" style="width: 1.25rem; height: 1.25rem;" />
                    @endif
                    <h3 class="text-base font-semibold {{ $step['is_valid'] ? 'text-green-900 dark:text-green-300' : 'text-red-900 dark:text-red-300' }}">
                        {{ $step['name'] }}
                    </h3>
                </div>
                
                @if(!$step['is_valid'])
                    <div class="mt-3 pl-8 text-sm text-red-600 dark:text-red-400">
                        <ul class="list-disc space-y-1">
                            @foreach($step['missing'] as $msg)
                                <li>{{ $msg }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
</div>
