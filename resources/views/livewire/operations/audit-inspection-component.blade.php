<div @mount-edit-item.window="$wire.mountEditItem($event.detail.itemId)" style="display: flex; flex-direction: column; gap: 1.5rem;">
    @php
        $totalItems = $audit->items->count();
        $pendingItems = $audit->items->where('status', \App\Domain\Audit\Enums\ItemStatus::PENDING)->count();
        $inspectedItems = $totalItems - $pendingItems;
        $progress = $totalItems > 0 ? round(($inspectedItems / $totalItems) * 100) : 0;
    @endphp

    <!-- Unified Audit Header & Control Card -->
    <x-filament::section>
        <div style="display: flex; flex-direction: column; gap: 1.25rem; width: 100%;">
            
            <!-- Row 1: Audit Info & Single Primary Action Button at Top Right -->
            <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                        <h3 style="font-size: 1.25rem; font-weight: 700; margin: 0; color: rgba(17, 24, 39, 1);">
                            {{ $audit->property->building_name ?? 'Property' }}
                            @if($audit->property?->code)
                                <span style="font-size: 1rem; font-weight: 500; color: rgba(107, 114, 128, 1);">({{ $audit->property->code }})</span>
                            @endif
                        </h3>
                        <x-filament::badge :color="$audit->status?->getColor() ?? 'gray'" size="md">
                            {{ $audit->status?->getLabel() ?? 'Draft' }}
                        </x-filament::badge>
                    </div>

                    <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); margin-top: 0.375rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <span>Audit: <strong>{{ $audit->audit_number }}</strong></span>
                        <span>&bull;</span>
                        <span>Type: <strong>{{ $audit->audit_type?->getLabel() }}</strong></span>
                        <span>&bull;</span>
                        <span>Inspector: <strong>{{ $audit->inspector?->name ?? 'Unassigned' }}</strong></span>
                    </div>
                </div>

                <!-- SINGLE Primary Action Button (Top Right) -->
                <div style="display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;">
                    @if($audit->status === \App\Domain\Audit\Enums\AuditStatus::DRAFT)
                        {{ $this->startAuditAction }}
                    @elseif($audit->canSubmit())
                        {{ $this->submitForReviewAction }}
                    @endif
                </div>
            </div>

            <!-- Row 2: Status Context Notice Banner -->
            @if($audit->status === \App\Domain\Audit\Enums\AuditStatus::DRAFT)
                <div style="background-color: rgba(239, 246, 255, 1); border: 1px solid rgba(191, 219, 254, 1); color: rgba(30, 64, 175, 1); padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                    <x-filament::icon icon="heroicon-o-play" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                    <span>This audit is in draft status. Click <strong>Start Audit</strong> to begin recording findings.</span>
                </div>
            @elseif(in_array($audit->status, [\App\Domain\Audit\Enums\AuditStatus::PENDING_REVIEW, \App\Domain\Audit\Enums\AuditStatus::IN_REVIEW]))
                <div style="background-color: rgba(254, 243, 199, 1); border: 1px solid rgba(252, 211, 77, 1); color: rgba(146, 64, 14, 1); padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                    <x-filament::icon icon="heroicon-o-lock-closed" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                    <span>This inspection has been submitted for approval and is currently locked for editing.</span>
                </div>
            @elseif($audit->status === \App\Domain\Audit\Enums\AuditStatus::PARTIALLY_APPROVED)
                <div style="background-color: rgba(254, 242, 242, 1); border: 1px solid rgba(252, 165, 165, 1); color: rgba(153, 27, 27, 1); padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                    <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                    <span><strong>Changes Requested:</strong> Please review and edit the rejected items highlighted below, then resubmit for approval.</span>
                </div>
            @elseif($audit->status === \App\Domain\Audit\Enums\AuditStatus::IN_PROGRESS)
                @if($pendingItems > 0)
                    <div style="background-color: rgba(254, 249, 195, 1); border: 1px solid rgba(253, 224, 71, 1); color: rgba(133, 77, 14, 1); padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                        <x-filament::icon icon="heroicon-o-information-circle" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                        <span>All audit items have to be inspected before submitting for review. (<strong>{{ $pendingItems }}</strong> item(s) pending inspection)</span>
                    </div>
                @else
                    <div style="background-color: rgba(240, 253, 244, 1); border: 1px solid rgba(187, 247, 208, 1); color: rgba(22, 101, 52, 1); padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                        <x-filament::icon icon="heroicon-o-check-circle" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                        <span><strong>All Items Inspected!</strong> Click <strong>Submit for Approval</strong> to send this audit for review.</span>
                    </div>
                @endif
            @elseif(in_array($audit->status, [\App\Domain\Audit\Enums\AuditStatus::APPROVED, \App\Domain\Audit\Enums\AuditStatus::COMPLETED]))
                <div style="background-color: rgba(240, 253, 244, 1); border: 1px solid rgba(187, 247, 208, 1); color: rgba(22, 101, 52, 1); padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                    <x-filament::icon icon="heroicon-o-check-badge" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                    <span>This inspection has been approved and synced with the property resource. It is fully locked.</span>
                </div>
            @endif

            <!-- Row 3: Progress Bar & Counter -->
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.875rem; font-weight: 500; color: rgba(55, 65, 81, 1); margin-bottom: 0.375rem;">
                    <span>Inspection Progress</span>
                    <span>{{ $inspectedItems }} / {{ $totalItems }} Items Inspected ({{ $progress }}%)</span>
                </div>
                <div style="width: 100%; background-color: rgba(156, 163, 175, 0.2); border-radius: 9999px; height: 0.5rem; overflow: hidden;">
                    <div style="background-color: var(--primary-600); height: 100%; border-radius: 9999px; transition: all 0.5s; width: {{ $progress }}%;"></div>
                </div>
            </div>

        </div>
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
                        {{ $category->items->where('status', '!=', \App\Domain\Audit\Enums\ItemStatus::PENDING)->count() }} / {{ $category->items->count() }}
                    </x-slot>
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <!-- Active Category Content -->
        <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1.5rem;">
            @php
                $activeCategory = $audit->categories->firstWhere('id', $activeCategoryId);
                $activeCategoryName = strtolower($activeCategory?->name ?? '');
            @endphp
            
            @if($activeCategory && $activeCategory->items->isNotEmpty())
                @foreach($activeCategory->items as $item)
                    @php
                        $isRejected = $item->status === \App\Domain\Audit\Enums\ItemStatus::REJECTED;
                        $lastReview = $item->reviews()->orderBy('created_at', 'desc')->first();
                    @endphp
                    <x-filament::section compact>
                        <div wire:click="mountAction('editItem', { item_id: '{{ $item->id }}' })" 
                             style="display: flex; flex-direction: column; gap: 0.5rem; cursor: pointer; width: 100%;">
                            
                            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                                <div style="display: flex; align-items: center; gap: 1rem;">
                                    <div style="flex-shrink: 0;">
                                        @if($isRejected)
                                            <div style="width: 2.5rem; height: 2.5rem; border-radius: 9999px; background-color: rgba(254, 226, 226, 1); display: flex; align-items: center; justify-content: center;">
                                                <x-filament::icon icon="heroicon-o-x-circle" style="width: 1.25rem; height: 1.25rem; color: rgba(220, 38, 38, 1);" />
                                            </div>
                                        @elseif($item->status === \App\Domain\Audit\Enums\ItemStatus::PENDING)
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
                                        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                            <h4 style="font-size: 1rem; font-weight: 500; margin: 0;">{{ $item->name }}</h4>
                                            @if(!empty($item->snapshot_data['is_new']))
                                                <x-filament::badge color="warning" size="sm">
                                                    Added in Audit
                                                </x-filament::badge>
                                            @endif
                                            @if($isRejected)
                                                <x-filament::badge color="danger" size="sm">
                                                    Rejected - Requires Revision
                                                </x-filament::badge>
                                            @endif
                                        </div>
                                        <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); margin-top: 0.25rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
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

                                    <x-filament::icon icon="heroicon-m-chevron-right" style="width: 1.25rem; height: 1.25rem; color: rgba(156, 163, 175, 1);" />
                                </div>
                            </div>

                            @if($isRejected && $lastReview && $lastReview->comments)
                                <div style="margin-top: 0.5rem; font-size: 0.875rem; color: rgba(153, 27, 27, 1); background: rgba(254, 242, 242, 1); padding: 0.625rem 0.875rem; border-radius: 0.375rem; border: 1px solid rgba(254, 202, 202, 1);">
                                    <strong>Rejection Reason ({{ $lastReview->comment_type ?? 'Issue' }}):</strong> {{ $lastReview->comments }}
                                </div>
                            @endif

                        </div>
                    </x-filament::section>
                @endforeach
            @else
                <div style="text-align: center; padding: 1.5rem; color: rgba(107, 114, 128, 1);">
                    No items in this category yet. Click below to add an item.
                </div>
            @endif
        </div>
        <div style="margin-top: 1.5rem; display: flex; justify-content: flex-end;">
            @if($activeCategoryName === 'rooms')
                {{ $this->createRoomAction }}
            @elseif($activeCategoryName === 'inventory')
                {{ $this->createInventoryAction }}
            @elseif($activeCategoryName === 'utilities')
                {{ $this->createUtilityAction }}
            @else
                {{ $this->createItemAction }}
            @endif
        </div>
    @endif

    <x-filament-actions::modals />

    @if($editingEvidenceId)
        <div wire:key="editor-wrapper-{{ $editingEvidenceId }}" @annotation-saved.window="$wire.closeEditor()">
            <livewire:operations.evidence-annotation-editor
                :evidence="\App\Domain\Audit\Models\AuditEvidence::find($editingEvidenceId)"
                :key="'editor-'.$editingEvidenceId"
            />
        </div>
    @endif

</div>
