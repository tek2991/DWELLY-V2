<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    <!-- Progress Indicator -->
    @php
        $totalItems = $audit->items->count();
        $inspectedItems = $audit->items->whereIn('status', [\App\Domain\Audit\Enums\ItemStatus::INSPECTED, \App\Domain\Audit\Enums\ItemStatus::VERIFIED])->count();
        $progress = $totalItems > 0 ? round(($inspectedItems / $totalItems) * 100) : 0;
    @endphp
    
    <x-filament::section>
        <x-slot name="heading">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <span style="font-size: 0.875rem; font-weight: 500;">Inspection Progress</span>
                <span style="font-size: 0.875rem; font-weight: 500;">{{ $inspectedItems }} / {{ $totalItems }} Items ({{ $progress }}%)</span>
            </div>
            <div style="width: 100%; background-color: rgba(156, 163, 175, 0.2); border-radius: 9999px; height: 0.5rem; margin-top: 0.75rem; overflow: hidden;">
                <div style="background-color: var(--primary-600); height: 100%; border-radius: 9999px; transition: all 0.5s; width: {{ $progress }}%;"></div>
            </div>
        </x-slot>
    </x-filament::section>

    @if($audit->categories->isEmpty())
        <div style="text-align: center; padding: 2rem 0; color: rgba(107, 114, 128, 1);">No categories found in this audit.</div>
    @else
        <!-- Tabs -->
        <x-filament::tabs label="Audit Categories">
            @foreach($audit->categories as $category)
                <x-filament::tabs.item
                    :active="$activeCategoryId === $category->id"
                    wire:click="setActiveCategory('{{ $category->id }}')"
                >
                    {{ $category->name }}
                    <x-slot name="badge">
                        {{ $category->items->whereIn('status', [\App\Domain\Audit\Enums\ItemStatus::INSPECTED, \App\Domain\Audit\Enums\ItemStatus::VERIFIED])->count() }} / {{ $category->items->count() }}
                    </x-slot>
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <!-- Active Category Content -->
        <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1.5rem;">
            @php
                $activeCategory = $audit->categories->firstWhere('id', $activeCategoryId);
            @endphp
            
            @if($activeCategory)
                @foreach($activeCategory->items as $item)
                    <x-filament::section compact>
                        <div wire:click="mountAction('editItem', { item_id: '{{ $item->id }}' })" 
                             style="display: flex; align-items: center; justify-content: space-between; cursor: pointer; width: 100%;">
                            
                            <div style="display: flex; align-items: center; gap: 1rem;">
                                <div style="flex-shrink: 0;">
                                    @if($item->status === \App\Domain\Audit\Enums\ItemStatus::PENDING)
                                        <div style="width: 2.5rem; height: 2.5rem; border-radius: 9999px; background-color: rgba(156, 163, 175, 0.2); display: flex; align-items: center; justify-content: center;">
                                            <x-filament::icon icon="heroicon-o-clock" style="width: 1.25rem; height: 1.25rem; color: rgba(156, 163, 175, 1);" />
                                        </div>
                                    @else
                                        <div style="width: 2.5rem; height: 2.5rem; border-radius: 9999px; background-color: rgba(34, 197, 94, 0.2); display: flex; align-items: center; justify-content: center;">
                                            <x-filament::icon icon="heroicon-o-check-circle" style="width: 1.25rem; height: 1.25rem; color: rgba(21, 128, 61, 1);" />
                                        </div>
                                    @endif
                                </div>
                                <div>
                                    <h4 style="font-size: 1rem; font-weight: 500; margin: 0;">{{ $item->name }}</h4>
                                    <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); margin-top: 0.25rem; display: flex; align-items: center; gap: 0.5rem;">
                                        <span>{{ $item->snapshot_data['display_name'] ?? ($item->snapshot_data['brand'] ?? 'Item Details') }}</span>
                                        
                                        @php
                                            $refKey = $item->source_type . '_' . $item->source_id;
                                            $prevCondition = $referenceItems[$refKey] ?? null;
                                        @endphp
                                        
                                        @if($prevCondition)
                                            <span>&bull;</span>
                                            <span style="font-size: 0.75rem; font-weight: 500; text-decoration: line-through;">Was: {{ $prevCondition }}</span>
                                        @endif

                                        @if($item->condition)
                                            <span>&bull;</span>
                                            <x-filament::badge :color="$item->condition->getColor()" size="sm">
                                                {{ $item->condition->getLabel() }}
                                            </x-filament::badge>
                                        @endif
                                    </div>
                                </div>
                            </div>

                        <div style="display: flex; align-items: center; gap: 0.75rem;">
                                @if($item->evidence()->count() > 0)
                                    <div style="display: flex; align-items: center; font-size: 0.875rem; color: rgba(107, 114, 128, 1);">
                                        <x-filament::icon icon="heroicon-o-camera" style="width: 1rem; height: 1rem; margin-right: 0.25rem;" />
                                        {{ $item->evidence()->count() }}
                                    </div>
                                @endif
                                <button
                                    type="button"
                                    wire:click.stop="mountAction('evidence', { item_id: '{{ $item->id }}' })"
                                    style="font-size: 0.75rem; font-weight: 600; padding: 0.25rem 0.6rem; color: rgba(99, 102, 241, 1); background: rgba(238, 242, 255, 1); border: none; border-radius: 0.375rem; cursor: pointer;"
                                >
                                    Evidence
                                </button>
                                <x-filament::icon icon="heroicon-m-chevron-right" style="width: 1.25rem; height: 1.25rem; color: rgba(156, 163, 175, 1);" />
                            </div>
                        </div>
                    </x-filament::section>
                @endforeach
            @endif
        </div>
    @endif

    <x-filament-actions::modals />

    @if($editingEvidenceId)
        <div @annotation-saved.window="$wire.closeEditor()">
            <livewire:operations.evidence-annotation-editor
                :evidence="\App\Domain\Audit\Models\AuditEvidence::find($editingEvidenceId)"
                :key="'editor-'.$editingEvidenceId"
            />
        </div>
    @endif

    @if($editingAnnotoriousEvidenceId)
        <div @annotation-saved.window="$wire.closeEditor()">
            <livewire:operations.annotorious-editor
                :evidence="\App\Domain\Audit\Models\AuditEvidence::find($editingAnnotoriousEvidenceId)"
                :key="'annotorious-editor-'.$editingAnnotoriousEvidenceId"
            />
        </div>
    @endif
</div>
