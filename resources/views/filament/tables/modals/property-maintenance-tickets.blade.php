@php
    use App\Domain\Maintenance\Enums\MaintenancePriority;
    use App\Domain\Maintenance\Enums\MaintenanceStatus;
    use App\Filament\Resources\Operations\MaintenanceRequestResource;

    $ticketsList = $tickets ?? $property->openMaintenanceRequests;
@endphp

<div class="space-y-4">
    <!-- Property Context Header -->
    <div class="flex items-center justify-between p-3.5 bg-gray-50 dark:bg-gray-800/60 rounded-xl border border-gray-200/80 dark:border-gray-700">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-danger-50 dark:bg-danger-950/40 border border-danger-200 dark:border-danger-800 flex items-center justify-center text-danger-600 dark:text-danger-400">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z" />
                </svg>
            </div>
            <div>
                <h4 class="font-bold text-gray-900 dark:text-gray-100 text-sm leading-snug">
                    {{ $property->building_name }}
                    <span class="text-xs font-mono font-semibold px-2 py-0.5 rounded bg-gray-200/70 dark:bg-gray-700 text-gray-700 dark:text-gray-300 ml-1">
                        {{ $property->code }}
                    </span>
                </h4>
                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                    @if($property->localityRef?->name)
                        📍 {{ $property->localityRef->name }}, {{ $property->localityRef->city?->name ?? $property->city }}
                    @elseif($property->city)
                        📍 {{ $property->city }}
                    @else
                        📍 {{ $property->address_line_1 }}
                    @endif
                </p>
            </div>
        </div>
        <div class="text-right">
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-danger-100 text-danger-800 dark:bg-danger-900/50 dark:text-danger-300 border border-danger-200 dark:border-danger-800">
                {{ $ticketsList->count() }} Open {{ Str::plural('Ticket', $ticketsList->count()) }}
            </span>
        </div>
    </div>

    <!-- Ticket Cards List -->
    <div class="space-y-3 max-h-[60vh] overflow-y-auto pr-1">
        @forelse($ticketsList as $ticket)
            @php
                $editUrl = MaintenanceRequestResource::getUrl('edit', ['record' => $ticket]);
                $priority = $ticket->priority instanceof MaintenancePriority ? $ticket->priority : MaintenancePriority::tryFrom((string) $ticket->priority);
                $status = $ticket->status instanceof MaintenanceStatus ? $ticket->status : MaintenanceStatus::tryFrom((string) $ticket->status);
                
                $priorityClasses = match($priority) {
                    MaintenancePriority::EMERGENCY => 'bg-red-50 text-red-700 border-red-200 dark:bg-red-950/50 dark:text-red-300 dark:border-red-800',
                    MaintenancePriority::HIGH => 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-950/50 dark:text-amber-300 dark:border-amber-800',
                    MaintenancePriority::MEDIUM => 'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-950/50 dark:text-sky-300 dark:border-sky-800',
                    default => 'bg-gray-50 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
                };

                $statusClasses = match($status) {
                    MaintenanceStatus::IN_PROGRESS => 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-950/50 dark:text-blue-300 dark:border-blue-800',
                    MaintenanceStatus::WORK_COMPLETED => 'bg-teal-50 text-teal-700 border-teal-200 dark:bg-teal-950/50 dark:text-teal-300 dark:border-teal-800',
                    MaintenanceStatus::RESOLVED, MaintenanceStatus::CLOSED => 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-950/50 dark:text-emerald-300 dark:border-emerald-800',
                    MaintenanceStatus::QUOTED, MaintenanceStatus::QUOTATION_PENDING => 'bg-orange-50 text-orange-700 border-orange-200 dark:bg-orange-950/50 dark:text-orange-300 dark:border-orange-800',
                    default => 'bg-gray-100 text-gray-700 border-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-700',
                };
            @endphp

            <div class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800/90 hover:border-gray-300 dark:hover:border-gray-600 transition shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <div class="space-y-1.5 flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap text-xs">
                        <span class="font-mono font-bold text-gray-900 dark:text-gray-100 bg-gray-100 dark:bg-gray-700 px-2 py-0.5 rounded">
                            {{ $ticket->ticket_number ?: "#{$ticket->id}" }}
                        </span>
                        
                        @if($priority)
                            <span class="px-2 py-0.5 rounded-full font-medium border text-[11px] {{ $priorityClasses }}">
                                {{ $priority->getLabel() }}
                            </span>
                        @endif

                        @if($status)
                            <span class="px-2 py-0.5 rounded-full font-medium border text-[11px] {{ $statusClasses }}">
                                {{ $status->getLabel() }}
                            </span>
                        @endif

                        <span class="text-gray-400 dark:text-gray-500">
                            • Opened {{ $ticket->created_at?->diffForHumans() }}
                        </span>
                    </div>

                    <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100 break-words leading-tight">
                        {{ $ticket->title }}
                    </h5>

                    @if($ticket->description)
                        <p class="text-xs text-gray-500 dark:text-gray-400 line-clamp-2">
                            {{ $ticket->description }}
                        </p>
                    @endif

                    <div class="flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400 pt-1 flex-wrap">
                        @if($ticket->payer_type)
                            <span class="inline-flex items-center gap-1">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Payer:</span>
                                {{ ucfirst(is_string($ticket->payer_type) ? $ticket->payer_type : $ticket->payer_type->value) }}
                            </span>
                        @endif

                        @if(($cost = $ticket->total_client_cost ?: $ticket->total_cost) > 0)
                            <span class="inline-flex items-center gap-1 font-semibold text-gray-800 dark:text-gray-200">
                                <span>Cost:</span> ₹{{ number_format($cost, 2) }}
                            </span>
                        @endif

                        @if($ticket->assignedInspector)
                            <span class="inline-flex items-center gap-1">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Inspector:</span>
                                {{ $ticket->assignedInspector->name }}
                            </span>
                        @endif

                        @if($ticket->vendor)
                            <span class="inline-flex items-center gap-1">
                                <span class="font-medium text-gray-700 dark:text-gray-300">Vendor:</span>
                                {{ $ticket->vendor->display_name }}
                            </span>
                        @endif
                    </div>
                </div>

                <div class="flex sm:flex-col items-center justify-end gap-2 flex-shrink-0">
                    <a href="{{ $editUrl }}" 
                       target="_blank" 
                       rel="noopener noreferrer"
                       class="inline-flex items-center justify-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-primary-600 hover:bg-primary-500 text-white transition shadow-sm w-full sm:w-auto">
                        <span>Open Ticket</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                        </svg>
                    </a>
                </div>
            </div>
        @empty
            <div class="text-center py-8 text-gray-500 dark:text-gray-400 text-sm">
                No active maintenance requests for this property.
            </div>
        @endforelse
    </div>

    <!-- Modal Footer Link -->
    <div class="pt-3 border-t border-gray-200 dark:border-gray-700 flex justify-between items-center text-xs">
        <a href="{{ MaintenanceRequestResource::getUrl('index', ['tableFilters' => ['property' => ['value' => $property->id]]]) }}"
           target="_blank"
           rel="noopener noreferrer"
           class="inline-flex items-center gap-1 text-primary-600 dark:text-primary-400 hover:underline font-medium">
            <span>View all property tickets in Maintenance Requests list</span>
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3" />
            </svg>
        </a>
    </div>
</div>
