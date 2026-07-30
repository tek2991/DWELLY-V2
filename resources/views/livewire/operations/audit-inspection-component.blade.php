<div @mount-edit-item.window="$wire.mountEditItem($event.detail.itemId)" style="display: flex; flex-direction: column; gap: 1.5rem;">
    @php
        $totalItems = $audit->items->count();
        $pendingItems = $audit->items->where('status', \App\Domain\Audit\Enums\ItemStatus::PENDING)->count();
        $inspectedItems = $totalItems - $pendingItems;
        $hasVideo = $audit->getFirstMedia('layout_video') !== null;
        
        $totalRequirements = $totalItems + 1; // +1 for mandatory layout video
        $completedRequirements = $inspectedItems + ($hasVideo ? 1 : 0);
        $progress = $totalRequirements > 0 ? round(($completedRequirements / $totalRequirements) * 100) : 0;
    @endphp

    <!-- Unified Audit Header & Control Card -->
    <x-filament::section>
        <div style="display: flex; flex-direction: column; gap: 1.25rem; width: 100%;">
            
            <!-- Row 1: Audit Info & Single Primary Action Button at Top Right -->
            <x-audit-header :audit="$audit">
                <x-slot name="actions">
                    @if($audit->status === \App\Domain\Audit\Enums\AuditStatus::DRAFT)
                        {{ $this->startAuditAction }}
                    @elseif($audit->canSubmit())
                        {{ $this->submitForReviewAction }}
                    @endif
                </x-slot>
            </x-audit-header>

            <!-- Row 2: Status & Permission Notice Banners -->
            @if(!$audit->isInspector())
                <div style="background-color: rgba(254, 242, 242, 1); border: 1px solid rgba(252, 165, 165, 1); color: rgba(153, 27, 27, 1); padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                    <x-filament::icon icon="heroicon-o-lock-closed" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                    <span><strong>Read-only Access:</strong> Only the assigned inspector (<strong>{{ $audit->inspector?->name ?? 'Unassigned' }}</strong>) can start, edit, or submit this audit inspection.</span>
                </div>
            @endif

            @if($audit->is_locked)
                <div style="background-color: rgba(254, 242, 242, 1); border: 1px solid rgba(252, 165, 165, 1); color: rgba(153, 27, 27, 1); padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                    <x-filament::icon icon="heroicon-o-lock-closed" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                    <span><strong>Permanently Locked:</strong> This audit is locked following downstream stage approval (such as signed MOU / tenancy activation) and cannot be edited or reopened.</span>
                </div>
            @elseif($audit->status === \App\Domain\Audit\Enums\AuditStatus::DRAFT)
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
                <div style="background-color: rgba(254, 242, 242, 1); border: 1px solid rgba(252, 165, 165, 1); color: rgba(153, 27, 27, 1); padding: 0.875rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; display: flex; flex-direction: column; gap: 0.5rem;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                        <span><strong>Changes Requested:</strong> Please review and update the audit as requested, then resubmit for approval.</span>
                    </div>
                    @if($audit->notes)
                        <div style="background: rgba(255, 255, 255, 0.85); border-radius: 0.375rem; padding: 0.625rem 0.875rem; font-size: 0.875rem; color: rgba(153, 27, 27, 1); border-left: 4px solid rgba(220, 38, 38, 1); margin-top: 0.25rem;">
                            📝 <strong>Approver Note:</strong> {{ $audit->notes }}
                        </div>
                    @endif
                </div>
            @elseif($audit->status === \App\Domain\Audit\Enums\AuditStatus::IN_PROGRESS)
                @if(!$hasVideo || $pendingItems > 0)
                    <div style="background-color: rgba(254, 249, 195, 1); border: 1px solid rgba(253, 224, 71, 1); color: rgba(133, 77, 14, 1); padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                        <x-filament::icon icon="heroicon-o-information-circle" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                        <span>
                            @if(!$hasVideo && $pendingItems > 0)
                                <strong>Property layout video</strong> must be uploaded and <strong>{{ $pendingItems }}</strong> item(s) pending inspection.
                            @elseif(!$hasVideo)
                                <strong>Property layout video</strong> must be uploaded before submitting for review.
                            @else
                                All audit items have to be inspected before submitting for review. (<strong>{{ $pendingItems }}</strong> item(s) pending inspection)
                            @endif
                        </span>
                    </div>
                @else
                    <div style="background-color: rgba(240, 253, 244, 1); border: 1px solid rgba(187, 247, 208, 1); color: rgba(22, 101, 52, 1); padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                        <x-filament::icon icon="heroicon-o-check-circle" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                        <span><strong>Inspection &amp; Layout Video Complete!</strong> Click <strong>Submit for Approval</strong> to send this audit for review.</span>
                    </div>
                @endif
            @elseif(in_array($audit->status, [\App\Domain\Audit\Enums\AuditStatus::APPROVED, \App\Domain\Audit\Enums\AuditStatus::COMPLETED]))
                <div style="background-color: rgba(240, 253, 244, 1); border: 1px solid rgba(187, 247, 208, 1); color: rgba(22, 101, 52, 1); padding: 0.75rem 1rem; border-radius: 0.375rem; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem;">
                    <x-filament::icon icon="heroicon-o-check-badge" style="width: 1.25rem; height: 1.25rem; flex-shrink: 0;" />
                    <span>This inspection has been approved and synced with the property resource.</span>
                </div>
            @endif

            <!-- Row 3: Progress Bar & Counter -->
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.875rem; font-weight: 500; color: rgba(55, 65, 81, 1); margin-bottom: 0.375rem;">
                    <span>Inspection Progress</span>
                    <span>{{ $completedRequirements }} / {{ $totalRequirements }} Tasks Completed ({{ $progress }}%)</span>
                </div>
                <div style="width: 100%; background-color: rgba(156, 163, 175, 0.2); border-radius: 9999px; height: 0.5rem; overflow: hidden;">
                    <div style="background-color: var(--primary-600); height: 100%; border-radius: 9999px; transition: all 0.5s; width: {{ $progress }}%;"></div>
                </div>
            </div>

        </div>
    </x-filament::section>

    @if($audit->categories->isEmpty() && !$audit->getFirstMedia('layout_video'))
        <div style="text-align: center; padding: 2rem 0; color: rgba(107, 114, 128, 1);">No categories found in this audit.</div>
    @else
        <!-- Tabs -->
        <x-filament::tabs label="Audit Categories">
            <x-filament::tabs.item
                :active="$activeCategoryId === 'layout_video'"
                wire:click="setActiveCategory('layout_video')"
                icon="heroicon-o-video-camera"
            >
                Property Video
                <x-slot name="badge">
                    {{ $hasVideo ? '1 / 1' : '0 / 1' }}
                </x-slot>
            </x-filament::tabs.item>

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

        <!-- Active Tab Content -->
        <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1.5rem;">
            @if($activeCategoryId === 'layout_video')
                <x-filament::section>
                    <x-slot name="heading">
                        <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; flex-wrap: wrap; gap: 0.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <x-filament::icon icon="heroicon-o-video-camera" style="width: 1.25rem; height: 1.25rem; color: var(--primary-600, #4f46e5);" />
                                <span style="font-size: 1.125rem; font-weight: 600;">Overall Property Layout Video</span>
                            </div>
                            <span style="font-size: 0.75rem; color: rgba(107, 114, 128, 1); background: rgba(243, 244, 246, 1); padding: 0.25rem 0.625rem; border-radius: 0.375rem;">
                                Layout Context
                            </span>
                        </div>
                    </x-slot>

                    @php
                        $layoutVideoMedia = $audit->getFirstMedia('layout_video');
                        $layoutVideoUrl = $layoutVideoMedia?->getUrl();
                    @endphp

                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        @if($layoutVideoUrl)
                            <div style="position: relative; width: 100%; max-height: 450px; background-color: #000; border-radius: 0.5rem; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(229, 231, 235, 1);">
                                <video controls preload="metadata" style="max-height: 450px; width: 100%; object-fit: contain;">
                                    <source src="{{ $layoutVideoUrl }}" type="{{ $layoutVideoMedia->mime_type ?? 'video/mp4' }}">
                                    Your browser does not support the video tag.
                                </video>
                            </div>

                            @if($this->isAuditEditable())
                                <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; padding-top: 0.5rem;">
                                    <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1);">
                                        File: <strong>{{ $layoutVideoMedia->file_name }}</strong> ({{ number_format($layoutVideoMedia->size / (1024 * 1024), 2) }} MB)
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                                        <label style="cursor: pointer; display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.875rem; font-weight: 500; padding: 0.375rem 0.75rem; background: rgba(243, 244, 246, 1); border: 1px solid rgba(209, 213, 219, 1); border-radius: 0.375rem; color: rgba(55, 65, 81, 1);">
                                            <x-filament::icon icon="heroicon-o-arrow-path" style="width: 1rem; height: 1rem;" />
                                            Replace Video
                                            <input type="file" wire:model="videoUpload" accept="video/*" capture="environment" style="display: none;">
                                        </label>
                                        <x-filament::button
                                            color="danger"
                                            icon="heroicon-o-trash"
                                            size="sm"
                                            outlined
                                            wire:click="deleteLayoutVideo"
                                            wire:confirm="Are you sure you want to remove the property layout video?"
                                        >
                                            Delete
                                        </x-filament::button>
                                    </div>
                                </div>
                            @endif
                        @else
                            <div style="border: 2px dashed rgba(209, 213, 219, 1); border-radius: 0.5rem; padding: 3rem 1.5rem; text-align: center; background-color: rgba(249, 250, 251, 1); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem;">
                                <div style="width: 3.5rem; height: 3.5rem; border-radius: 9999px; background-color: rgba(238, 242, 255, 1); display: flex; align-items: center; justify-content: center;">
                                    <x-filament::icon icon="heroicon-o-video-camera" style="width: 1.75rem; height: 1.75rem; color: var(--primary-600, #4f46e5);" />
                                </div>
                                <div>
                                    <h4 style="font-size: 1rem; font-weight: 600; margin: 0; color: rgba(17, 24, 39, 1);">No Property Layout Video Uploaded</h4>
                                    <p style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); margin: 0.25rem 0 0 0; max-width: 500px;">
                                        Upload an overall video walkthrough of the property to establish spatial and layout context for reviewers alongside specific detail photos.
                                    </p>
                                </div>

                                @if($this->isAuditEditable())
                                    <div style="margin-top: 0.5rem;" wire:loading.remove wire:target="videoUpload">
                                        <label style="cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; font-weight: 600; padding: 0.5rem 1rem; background-color: var(--primary-600, #4f46e5); color: white; border-radius: 0.375rem; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                                            <x-filament::icon icon="heroicon-o-arrow-up-tray" style="width: 1.25rem; height: 1.25rem;" />
                                            Upload Layout Video
                                            <input type="file" wire:model="videoUpload" accept="video/*" capture="environment" style="display: none;">
                                        </label>
                                    </div>
                                    <div wire:loading wire:target="videoUpload" style="margin-top: 0.5rem;">
                                        <div style="font-size: 0.875rem; font-weight: 500; color: var(--primary-600, #4f46e5); display: flex; align-items: center; gap: 0.5rem;">
                                            <x-filament::loading-indicator style="width: 1.25rem; height: 1.25rem;" />
                                            Uploading overall property video...
                                        </div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @else
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
        @endif
    </div>
    @if($activeCategoryId !== 'layout_video')
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
