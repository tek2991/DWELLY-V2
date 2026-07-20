<div style="display: flex; flex-direction: column; gap: 1.5rem; margin-top: 2rem;">
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
                        {{ $category->items->where('status', \App\Domain\Audit\Enums\ItemStatus::APPROVED)->count() }} / {{ $category->items->count() }}
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
                        <div style="display: flex; flex-direction: column; gap: 1rem; width: 100%;">
                            
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;">
                                <div style="display: flex; flex-direction: column; gap: 0.5rem; flex: 1;">
                                    <h4 style="font-size: 1rem; font-weight: 600; margin: 0; color: rgba(17, 24, 39, 1);">{{ $item->name }}</h4>
                                    
                                    <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); display: flex; align-items: center; gap: 0.5rem;">
                                        <span>{{ $item->snapshot_data['display_name'] ?? ($item->snapshot_data['brand'] ?? 'Item Details') }}</span>
                                        @if($item->condition)
                                            <span>&bull;</span>
                                            <x-filament::badge :color="$item->condition->getColor()" size="sm">
                                                {{ $item->condition->getLabel() }}
                                            </x-filament::badge>
                                        @endif
                                        @if($item->status === \App\Domain\Audit\Enums\ItemStatus::APPROVED)
                                            <span>&bull;</span>
                                            <x-filament::badge color="success" size="sm">
                                                Approved
                                            </x-filament::badge>
                                        @elseif($item->status === \App\Domain\Audit\Enums\ItemStatus::REJECTED)
                                            <span>&bull;</span>
                                            <x-filament::badge color="danger" size="sm">
                                                Rejected
                                            </x-filament::badge>
                                        @endif
                                    </div>

                                    @if($item->remarks)
                                        <div style="font-size: 0.875rem; color: rgba(75, 85, 99, 1); background: rgba(243, 244, 246, 1); padding: 0.5rem; border-radius: 0.25rem;">
                                            <strong>Inspector Remarks:</strong> {{ $item->remarks }}
                                        </div>
                                    @endif

                                    @php
                                        $lastReview = $item->reviews()->orderBy('created_at', 'desc')->first();
                                    @endphp
                                    @if($lastReview && $lastReview->status === 'rejected')
                                        <div style="font-size: 0.875rem; color: rgba(153, 27, 27, 1); background: rgba(254, 242, 242, 1); padding: 0.5rem; border-radius: 0.25rem; border: 1px solid rgba(254, 202, 202, 1);">
                                            <strong>Reviewer Rejected ({{ $lastReview->comment_type }}):</strong> {{ $lastReview->comments }}
                                        </div>
                                    @endif
                                </div>

                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    @if(!$item->isApproved() && $audit->canApprove())
                                        {{ ($this->approveItemAction)(['item_id' => $item->id]) }}
                                        {{ ($this->rejectItemAction)(['item_id' => $item->id]) }}
                                    @endif
                                    @if($item->isApproved() && empty($item->source_id))
                                        {{ ($this->syncToPropertyAction)(['item_id' => $item->id]) }}
                                    @endif
                                </div>
                            </div>

                            @if($item->evidence->isNotEmpty())
                                <div style="border-top: 1px solid rgba(229, 231, 235, 1); padding-top: 1rem; margin-top: 0.5rem;">
                                    <div style="font-size: 0.875rem; font-weight: 600; color: rgba(55, 65, 81, 1); margin-bottom: 0.75rem;">Evidence ({{ $item->evidence->count() }})</div>
                                    <div style="display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 0.5rem;">
                                        @foreach($item->evidence as $ev)
                                            @php
                                                $media = $ev->getFirstMedia('images');
                                                $url = $media ? $media->getUrl() : null;
                                            @endphp
                                            @if($url)
                                                <a href="{{ $url }}" target="_blank" style="flex-shrink: 0;">
                                                    <img src="{{ $url }}" style="height: 120px; width: 160px; object-fit: cover; border-radius: 0.5rem; border: 1px solid rgba(229, 231, 235, 1);">
                                                </a>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                            
                        </div>
                    </x-filament::section>
                @endforeach
            @endif
        </div>
    @endif

    <x-filament-actions::modals />
</div>
