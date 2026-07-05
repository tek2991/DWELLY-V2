<div class="space-y-4">
    @php
        $activities = $getRecord()->activities;
    @endphp

    @if($activities->isEmpty())
        <p class="text-sm text-gray-500">No activity recorded yet.</p>
    @else
        <ul class="relative border-l border-gray-200 dark:border-gray-700 ml-3">
            @foreach($activities as $activity)
                <li class="mb-4 ml-6">
                    <span class="absolute flex items-center justify-center w-6 h-6 bg-blue-100 rounded-full -left-3 ring-8 ring-white dark:ring-gray-900 dark:bg-blue-900">
                        <x-heroicon-s-clock class="w-3 h-3 text-blue-800 dark:text-blue-300" />
                    </span>
                    <h3 class="flex items-center mb-1 text-sm font-semibold text-gray-900 dark:text-white">
                        {{ \Illuminate\Support\Str::headline($activity->activity_type->value ?? $activity->activity_type) }}
                        @if($activity->metadata && isset($activity->metadata['subtitle']))
                            - {{ $activity->metadata['subtitle'] }}
                        @endif
                    </h3>
                    <time class="block mb-2 text-xs font-normal leading-none text-gray-400 dark:text-gray-500">
                        {{ $activity->performed_at->format('M j, Y h:i A') }} 
                        @if($activity->performedBy)
                            by {{ $activity->performedBy->name }}
                        @endif
                    </time>
                    @if($activity->notes)
                        <p class="mb-4 text-sm font-normal text-gray-500 dark:text-gray-400">{{ $activity->notes }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
