<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Left Sidebar: Project Info & Deliverables -->
        <div class="col-span-1 space-y-6">
            <x-filament::section>
                <x-slot name="heading">
                    Project Details
                </x-slot>
                
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500">Status</p>
                        <x-filament::badge color="{{ match($project->status) {
                            'created' => 'gray',
                            'in_progress' => 'warning',
                            'on_hold' => 'danger',
                            'completed' => 'success',
                            'cancelled' => 'danger',
                            default => 'gray',
                        } }}">
                            {{ Str::headline($project->status) }}
                        </x-filament::badge>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-500">Overall Progress</p>
                        <div class="flex items-center gap-4 mt-1">
                            <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                <div class="bg-primary-600 h-2.5 rounded-full" style="width: {{ (float) $project->progress }}%"></div>
                            </div>
                            <span class="text-sm font-medium">{{ (float) $project->progress }}%</span>
                        </div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section>
                <x-slot name="heading">
                    Deliverables
                </x-slot>
                
                @if($project->deliverables->isEmpty())
                    <p class="text-sm text-gray-500">No deliverables required.</p>
                @else
                    <ul class="space-y-2">
                        @foreach($project->deliverables as $deliverable)
                            <li class="flex items-center justify-between p-2 rounded-lg bg-gray-50 dark:bg-gray-800">
                                <div>
                                    <p class="text-sm font-medium">{{ $deliverable->name }}</p>
                                </div>
                                <div>
                                    @if($deliverable->name === 'Signed Management Agreement' && $project->entity && $project->entity->mou_id)
                                        {{ ($this->viewSignedPdfAction)(['mou_id' => $project->entity->mou_id]) }}
                                    @else
                                        <x-filament::badge color="{{ $deliverable->status === 'verified' ? 'success' : 'warning' }}">
                                            {{ Str::headline($deliverable->status) }}
                                        </x-filament::badge>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-filament::section>
        </div>

        <!-- Main Content: Phases & Stages -->
        <div class="col-span-1 md:col-span-2 space-y-6">
            @foreach($project->phases as $phase)
                <x-filament::section collapsible :collapsed="$phase->status === 'completed'">
                    <x-slot name="heading">
                        <div class="flex items-center justify-between w-full">
                            <span>{{ $phase->name }}</span>
                            <x-filament::badge color="{{ match($phase->status) {
                                'not_started' => 'gray',
                                'active' => 'primary',
                                'completed' => 'success',
                                default => 'gray',
                            } }}">
                                {{ Str::headline($phase->status) }}
                            </x-filament::badge>
                        </div>
                    </x-slot>
                    
                    <div class="space-y-6">
                        @foreach($phase->stages as $stage)
                            <div class="border rounded-lg p-4 dark:border-gray-700">
                                <div class="flex items-center justify-between mb-4">
                                    <h4 class="font-medium text-lg">{{ $stage->name }}</h4>
                                    <x-filament::badge color="{{ match($stage->status) {
                                        'not_started' => 'gray',
                                        'active' => 'primary',
                                        'completed' => 'success',
                                        'skipped' => 'gray',
                                        default => 'gray',
                                    } }}">
                                        {{ Str::headline($stage->status) }}
                                    </x-filament::badge>
                                </div>

                                @if($stage->tasks->isEmpty())
                                    <p class="text-sm text-gray-500">No tasks in this stage.</p>
                                @else
                                    <div class="space-y-4">
                                        @foreach($stage->tasks as $task)
                                            <div class="flex flex-col p-4 rounded-lg bg-gray-50 dark:bg-gray-800 gap-3">
                                                <div class="flex items-center justify-between">
                                                    <div>
                                                        <p class="font-medium">{{ $task->title }}</p>
                                                        @if($task->description)
                                                            <p class="text-sm text-gray-500 mt-1">{{ $task->description }}</p>
                                                        @endif
                                                    </div>
                                                    <div class="flex items-center gap-3">
                                                        @if($project->entity_type === \App\Domain\Property\Models\Property::class)
                                                            @if($task->title === 'Log all Furniture, Fixtures, and Appliances')
                                                                <x-filament::button tag="a" href="{{ App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $project->entity_id]) }}?activeRelationManager=1" target="_blank" size="sm" color="gray" icon="heroicon-o-archive-box">
                                                                    Manage Inventory
                                                                </x-filament::button>
                                                            @elseif($task->title === 'Define Room Configuration (Bedrooms, Bathrooms, Balconies)')
                                                                <x-filament::button tag="a" href="{{ App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $project->entity_id]) }}?activeRelationManager=0" target="_blank" size="sm" color="gray" icon="heroicon-o-home">
                                                                    Manage Rooms
                                                                </x-filament::button>
                                                            @elseif($task->title === 'Map Property Amenities')
                                                                <x-filament::button tag="a" href="{{ App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $project->entity_id]) }}?activeRelationManager=2" target="_blank" size="sm" color="gray" icon="heroicon-o-sparkles">
                                                                    Manage Amenities
                                                                </x-filament::button>
                                                            @elseif($task->title === 'Map Nearby Establishments and Landmarks')
                                                                <x-filament::button tag="a" href="{{ App\Filament\Resources\Properties\PropertyResource::getUrl('edit', ['record' => $project->entity_id]) }}?activeRelationManager=3" target="_blank" size="sm" color="gray" icon="heroicon-o-map-pin">
                                                                    Manage Establishments
                                                                </x-filament::button>
                                                            @endif
                                                        @endif

                                                        <x-filament::badge color="{{ match($task->status) {
                                                            'open' => 'gray',
                                                            'in_progress' => 'warning',
                                                            'completed' => 'success',
                                                            'blocked' => 'danger',
                                                            default => 'gray',
                                                        } }}">
                                                            {{ Str::headline($task->status) }}
                                                        </x-filament::badge>
                                                        
                                                        @if($task->status === 'open')
                                                            {{ ($this->startTaskAction)(['task_id' => $task->id]) }}
                                                        @endif

                                                        @if($task->status === 'in_progress')
                                                            {{ ($this->markTaskCompleteAction)(['task_id' => $task->id]) }}
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- Checklists -->
                                                @if($task->checklists->isNotEmpty())
                                                    <div class="mt-2 border-t pt-3 dark:border-gray-700">
                                                        <ul class="space-y-2">
                                                            @foreach($task->checklists as $item)
                                                                <li class="flex items-center gap-3">
                                                                    <input 
                                                                        type="checkbox" 
                                                                        wire:click="toggleChecklistItem('{{ $item->id }}')" 
                                                                        @if($item->is_completed) checked @endif
                                                                        @if($task->status === 'completed') disabled @endif
                                                                        class="rounded border-gray-300 text-primary-600 shadow-sm focus:border-primary-300 focus:ring focus:ring-primary-200 focus:ring-opacity-50"
                                                                    >
                                                                    <span class="text-sm {{ $item->is_completed ? 'line-through text-gray-400' : 'text-gray-700 dark:text-gray-200' }}">
                                                                        {{ $item->item_text }}
                                                                        @if($item->is_mandatory)
                                                                            <span class="text-danger-500 text-xs ml-1">*</span>
                                                                        @endif
                                                                    </span>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </x-filament::section>
            @endforeach
        </div>
        
    </div>
</x-filament-panels::page>
