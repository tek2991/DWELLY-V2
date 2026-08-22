<div style="display: flex; flex-direction: column; gap: 1.5rem;">
    @php
        $totalItems = $audit->items->count();
        $approvedItems = $audit->items->where('status', \App\Domain\Audit\Enums\ItemStatus::APPROVED)->count();
        $rejectedItems = $audit->items->where('status', \App\Domain\Audit\Enums\ItemStatus::REJECTED)->count();
        $pendingItems = $totalItems - $approvedItems - $rejectedItems;
    @endphp

    <!-- Audit Summary -->
    <x-filament::section>
        <x-slot name="heading">
            <x-audit-header :audit="$audit">
                <x-slot name="actions">
                    <span style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); background: rgba(243, 244, 246, 1); padding: 0.25rem 0.75rem; border-radius: 9999px;">
                        Review Round: <strong>{{ $audit->review_round }}</strong>
                    </span>

                    @if($audit->canReview())
                        {{ $this->acceptAllAction }}
                        {{ $this->approveAuditAction }}
                        {{ $this->requestChangesAction }}
                    @endif
                    @if($audit->canReopen())
                        {{ $this->reopenAuditAction }}
                    @endif
                </x-slot>
            </x-audit-header>
        </x-slot>
        

        
        <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-top: 1rem;">
            <div style="background: rgba(243, 244, 246, 1); padding: 1rem; border-radius: 0.5rem; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: rgba(17, 24, 39, 1);">{{ $totalItems }}</div>
                <div style="font-size: 0.875rem; font-weight: 500; color: rgba(107, 114, 128, 1); text-transform: uppercase;">Total Items</div>
            </div>
            
            <div style="background: rgba(220, 252, 231, 1); padding: 1rem; border-radius: 0.5rem; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: rgba(21, 128, 61, 1);">{{ $approvedItems }}</div>
                <div style="font-size: 0.875rem; font-weight: 500; color: rgba(22, 163, 74, 1); text-transform: uppercase;">Approved</div>
            </div>
            
            <div style="background: rgba(254, 242, 242, 1); padding: 1rem; border-radius: 0.5rem; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: rgba(220, 38, 38, 1);">{{ $rejectedItems }}</div>
                <div style="font-size: 0.875rem; font-weight: 500; color: rgba(239, 68, 68, 1); text-transform: uppercase;">Rejected</div>
            </div>
            
            <div style="background: rgba(254, 249, 195, 1); padding: 1rem; border-radius: 0.5rem; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: rgba(202, 138, 4, 1);">{{ $pendingItems }}</div>
                <div style="font-size: 0.875rem; font-weight: 500; color: rgba(234, 179, 8, 1); text-transform: uppercase;">Pending</div>
            </div>
        </div>
    </x-filament::section>



    @php
        $requiresVideo = $audit->audit_type !== \App\Domain\Audit\Enums\AuditType::MAINTENANCE;
    @endphp

    @if($audit->categories->isEmpty() && (!$requiresVideo || !$audit->getFirstMedia('layout_video')))
        <div style="text-align: center; padding: 2rem 0; color: rgba(107, 114, 128, 1);">No categories found in this audit.</div>
    @else
        <!-- Tabs -->
        <x-filament::tabs label="Audit Categories">
            @if($requiresVideo)
                @php
                    $hasLayoutVideo = $audit->getFirstMedia('layout_video') !== null;
                    $videoBadgeText = '0 / 1';
                    $videoBadgeColor = 'gray';
                    if ($hasLayoutVideo) {
                        if ($audit->video_status === 'approved') {
                            $videoBadgeText = 'Approved';
                            $videoBadgeColor = 'success';
                        } elseif ($audit->video_status === 'rejected') {
                            $videoBadgeText = 'Rejected';
                            $videoBadgeColor = 'danger';
                        } else {
                            $videoBadgeText = 'Pending';
                            $videoBadgeColor = 'warning';
                        }
                    }
                @endphp
                <x-filament::tabs.item
                    :active="$activeCategoryId === 'layout_video'"
                    wire:click="setActiveCategory('layout_video')"
                    icon="heroicon-o-video-camera"
                >
                    Property Video
                    <x-slot name="badge" :color="$videoBadgeColor">
                        {{ $videoBadgeText }}
                    </x-slot>
                </x-filament::tabs.item>
            @endif

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
        <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem;">
            @if($activeCategoryId === 'layout_video')
                @php
                    $refAuditForVideo = $audit->reference_audit_id ? \App\Domain\Audit\Models\Audit::find($audit->reference_audit_id) : null;
                    $refVideoMedia = $refAuditForVideo?->getFirstMedia('layout_video');
                    $refVideoUrl = $refVideoMedia?->getUrl();
                    $layoutVideoMedia = $audit->getFirstMedia('layout_video');
                    $layoutVideoUrl = $layoutVideoMedia?->getUrl();
                @endphp

                @if($refVideoUrl)
                    <x-filament::section style="border: 1px solid #fcd34d; background: #fffbeb;">
                        <x-slot name="heading">
                            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; flex-wrap: wrap; gap: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem;">
                                    <x-filament::icon icon="heroicon-o-document-duplicate" style="width: 1.25rem; height: 1.25rem; color: #d97706;" />
                                    <span style="font-size: 1.125rem; font-weight: 600; color: #92400e;">
                                        Previous Baseline Layout Video (Audit #{{ $refAuditForVideo->audit_number ?? $refAuditForVideo->id }})
                                    </span>
                                </div>
                                <span style="font-size: 0.75rem; color: #b45309; background: #fef3c7; padding: 0.25rem 0.625rem; border-radius: 0.375rem; font-weight: 600;">
                                    Reference Baseline
                                </span>
                            </div>
                        </x-slot>

                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            <div style="position: relative; width: 100%; max-height: 450px; background-color: #000; border-radius: 0.5rem; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 1px solid #fcd34d;">
                                <video controls preload="metadata" style="max-height: 450px; width: 100%; object-fit: contain;">
                                    <source src="{{ $refVideoUrl }}" type="{{ $refVideoMedia->mime_type ?? 'video/mp4' }}">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                            <div style="font-size: 0.875rem; color: #78350f; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                                <span>📹 Walkthrough video recorded during baseline Audit <strong>#{{ $refAuditForVideo->audit_number ?? $refAuditForVideo->id }}</strong>.</span>
                                <span>File: <strong>{{ $refVideoMedia->file_name }}</strong> ({{ number_format($refVideoMedia->size / (1024 * 1024), 2) }} MB)</span>
                            </div>
                        </div>
                    </x-filament::section>
                @endif

                @if($layoutVideoUrl)
                    <x-filament::section>
                        <x-slot name="heading">
                            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%; flex-wrap: wrap; gap: 0.5rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    <x-filament::icon icon="heroicon-o-video-camera" style="width: 1.25rem; height: 1.25rem; color: var(--primary-600, #4f46e5);" />
                                    <span style="font-size: 1.125rem; font-weight: 600;">Overall Property Layout Video</span>
                                    @if($audit->video_status === 'approved')
                                        <x-filament::badge color="success" size="sm">
                                            Approved
                                        </x-filament::badge>
                                    @elseif($audit->video_status === 'rejected')
                                        <x-filament::badge color="danger" size="sm">
                                            Rejected
                                        </x-filament::badge>
                                    @else
                                        <x-filament::badge color="info" size="sm">
                                            Awaiting Review
                                        </x-filament::badge>
                                    @endif
                                </div>
                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                    @if($audit->canReview())
                                        @if($audit->video_status === 'approved')
                                            {{ $this->rejectVideoAction }}
                                            {{ $this->resetVideoAction }}
                                        @elseif($audit->video_status === 'rejected')
                                            {{ $this->approveVideoAction }}
                                            {{ $this->resetVideoAction }}
                                        @else
                                            {{ $this->approveVideoAction }}
                                            {{ $this->rejectVideoAction }}
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </x-slot>

                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            @if($audit->video_status === 'rejected')
                                <div style="font-size: 0.875rem; color: #991b1b; background: #fef2f2; padding: 0.75rem 1rem; border-radius: 0.375rem; border: 1px solid #fecaca; display: flex; flex-direction: column; gap: 0.25rem;">
                                    <div style="font-weight: 600; display: flex; align-items: center; gap: 0.375rem;">
                                        <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width: 1rem; height: 1rem; color: #dc2626;" />
                                        <span>Reviewer Rejected ({{ $audit->video_rejection_type ?? 'Issue' }}):</span>
                                    </div>
                                    <div style="color: #7f1d1d; line-height: 1.4;">
                                        {{ $audit->video_rejection_reason }}
                                    </div>
                                    @if($audit->videoReviewedBy || $audit->video_reviewed_at)
                                        <div style="font-size: 0.75rem; color: #b91c1c; margin-top: 0.25rem;">
                                            Rejected by {{ $audit->videoReviewedBy?->name ?? 'Reviewer' }} on {{ $audit->video_reviewed_at?->format('d M Y, h:i A') }}
                                        </div>
                                    @endif
                                </div>
                            @elseif($audit->video_status === 'approved' && ($audit->videoReviewedBy || $audit->video_reviewed_at))
                                <div style="font-size: 0.8125rem; color: #166534; background: #f0fdf4; padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #bbf7d0; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                                    <span>✓ Video approved by <strong>{{ $audit->videoReviewedBy?->name ?? 'Reviewer' }}</strong></span>
                                    <span>{{ $audit->video_reviewed_at?->format('d M Y, h:i A') }}</span>
                                </div>
                            @endif

                            <div style="position: relative; width: 100%; max-height: 450px; background-color: #000; border-radius: 0.5rem; overflow: hidden; display: flex; align-items: center; justify-content: center; border: 1px solid rgba(229, 231, 235, 1);">
                                <video controls preload="metadata" style="max-height: 450px; width: 100%; object-fit: contain;">
                                    <source src="{{ $layoutVideoUrl }}" type="{{ $layoutVideoMedia->mime_type ?? 'video/mp4' }}">
                                    Your browser does not support the video tag.
                                </video>
                            </div>
                            <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
                                <span>📹 Overall walkthrough video for property layout context.</span>
                                <span>File: <strong>{{ $layoutVideoMedia->file_name }}</strong> ({{ number_format($layoutVideoMedia->size / (1024 * 1024), 2) }} MB)</span>
                            </div>
                        </div>
                    </x-filament::section>
                @else
                    <div style="border: 2px dashed rgba(209, 213, 219, 1); border-radius: 0.5rem; padding: 3rem 1.5rem; text-align: center; background-color: rgba(249, 250, 251, 1); display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 0.75rem;">
                        <div style="width: 3.5rem; height: 3.5rem; border-radius: 9999px; background-color: rgba(243, 244, 246, 1); display: flex; align-items: center; justify-content: center;">
                            <x-filament::icon icon="heroicon-o-video-camera" style="width: 1.75rem; height: 1.75rem; color: rgba(156, 163, 175, 1);" />
                        </div>
                        <div>
                            <h4 style="font-size: 1rem; font-weight: 600; margin: 0; color: rgba(17, 24, 39, 1);">No Property Layout Video Uploaded</h4>
                            <p style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); margin: 0.25rem 0 0 0;">
                                No overall video walkthrough was recorded during this property audit inspection.
                            </p>
                        </div>
                    </div>
                @endif
            @else
                @php
                    $activeCategory = $audit->categories->firstWhere('id', $activeCategoryId);
                @endphp
            
            @if($activeCategory)
                @foreach($activeCategory->items as $item)
                    <x-filament::section compact>
                        <div style="display: flex; flex-direction: column; gap: 1rem; width: 100%;">
                            
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;">
                                <div style="display: flex; flex-direction: column; gap: 0.5rem; flex: 1;">
                                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                        <h4 style="font-size: 1rem; font-weight: 600; margin: 0; color: rgba(17, 24, 39, 1);">{{ $item->name }}</h4>
                                        @if(!empty($item->snapshot_data['is_new']))
                                            <x-filament::badge color="warning" size="sm">
                                                Added in Audit
                                            </x-filament::badge>
                                        @endif
                                        @if(!empty($item->snapshot_data['exclude_from_sync']))
                                            <x-filament::badge color="gray" size="sm">
                                                🚫 Excluded
                                            </x-filament::badge>
                                        @endif
                                    </div>
                                    
                                    @php
                                        $refKey = ($item->source_type && $item->source_id) 
                                            ? ($item->source_type . '_' . $item->source_id) 
                                            : ('name_' . mb_strtolower(trim($item->name)));
                                        $refData = $referenceItems[$refKey] ?? null;
                                    @endphp

                                    <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                                        <span>{{ $item->snapshot_data['display_name'] ?? ($item->snapshot_data['brand'] ?? 'Item Details') }}</span>

                                        @if(!empty($refData['condition']))
                                            <span>&bull;</span>
                                            <span style="font-size: 0.75rem; font-weight: 600; color: #4b5563; display: inline-flex; align-items: center; gap: 0.25rem;">
                                                Baseline:
                                                <x-filament::badge :color="$refData['condition_color'] ?? 'gray'" size="sm">
                                                    {{ $refData['condition'] }}
                                                </x-filament::badge>
                                            </span>
                                        @endif

                                        @if($item->condition)
                                            <span>&bull;</span>
                                            <span style="font-size: 0.75rem; font-weight: 600; color: #4b5563; display: inline-flex; align-items: center; gap: 0.25rem;">
                                                Current:
                                                <x-filament::badge :color="$item->condition->getColor()" size="sm">
                                                    {{ $item->condition->getLabel() }}
                                                </x-filament::badge>
                                            </span>
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
                                        @elseif($item->status === \App\Domain\Audit\Enums\ItemStatus::INSPECTED)
                                            <span>&bull;</span>
                                            <x-filament::badge color="info" size="sm">
                                                Inspected (Awaiting Review)
                                            </x-filament::badge>
                                        @endif
                                    </div>

                                    @if(!empty($refData['remarks']))
                                        <div style="font-size: 0.8125rem; color: #374151; background: #f3f4f6; padding: 0.375rem 0.625rem; border-radius: 0.375rem; border-left: 3px solid #9ca3af; margin-top: 0.25rem;">
                                            <strong>Previous Remarks:</strong> {{ $refData['remarks'] }}
                                        </div>
                                    @endif

                                    @if($item->remarks)
                                        <div style="font-size: 0.875rem; color: rgba(75, 85, 99, 1); background: rgba(243, 244, 246, 1); padding: 0.5rem 0.75rem; border-radius: 0.25rem; margin-top: 0.25rem;">
                                            <strong>Inspector Remarks ({{ $audit->inspector?->name ?? $audit->completedBy?->name ?? 'Inspector' }}):</strong> {{ $item->remarks }}
                                        </div>
                                    @endif

                                    @php
                                        $lastReview = $item->reviews()->orderBy('created_at', 'desc')->first();
                                    @endphp
                                    @if($lastReview && $lastReview->status === 'rejected')
                                        <div style="font-size: 0.875rem; color: rgba(153, 27, 27, 1); background: rgba(254, 242, 242, 1); padding: 0.5rem 0.75rem; border-radius: 0.25rem; border: 1px solid rgba(254, 202, 202, 1);">
                                            <strong>Reviewer Rejected ({{ $lastReview->comment_type ?? 'Issue' }}):</strong> {{ $lastReview->comments }}
                                        </div>
                                    @endif


                                </div>

                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0; flex-wrap: wrap;">
                                    @if($audit->canReview())
                                        @if($item->isApproved())
                                            {{ ($this->rejectItemAction)(['item_id' => $item->id]) }}
                                            {{ ($this->resetItemAction)(['item_id' => $item->id]) }}
                                        @elseif($item->isRejected())
                                            {{ ($this->approveItemAction)(['item_id' => $item->id]) }}
                                            {{ ($this->resetItemAction)(['item_id' => $item->id]) }}
                                        @else
                                            {{ ($this->approveItemAction)(['item_id' => $item->id]) }}
                                            {{ ($this->rejectItemAction)(['item_id' => $item->id]) }}
                                        @endif

                                        @if(!empty($item->snapshot_data['is_new']))
                                            {{ ($this->toggleExcludeFromSyncAction)(['item_id' => $item->id]) }}
                                        @endif
                                    @endif
                                    @if($item->isApproved() && empty($item->source_id))
                                        {{ ($this->syncToPropertyAction)(['item_id' => $item->id]) }}
                                    @endif
                                </div>
                            @if(!empty($refData['evidence']) && count($refData['evidence']) > 0)
                                <div style="border-top: 1px dashed rgba(209, 213, 219, 1); padding-top: 0.875rem; margin-top: 0.5rem;">
                                    <div style="font-size: 0.875rem; font-weight: 600; color: #d97706; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.375rem;">
                                        <x-filament::icon icon="heroicon-o-document-duplicate" style="width: 1rem; height: 1rem; color: #d97706;" />
                                        <span>Reference Baseline Photos ({{ count($refData['evidence']) }})</span>
                                    </div>
                                    <div style="display: flex; gap: 0.75rem; overflow-x: auto; padding-bottom: 0.375rem;">
                                        @foreach($refData['evidence'] as $ev)
                                            <div 
                                                x-data
                                                data-image-url="{{ $ev['url'] }}"
                                                data-annotation-json="{{ json_encode($ev['annotation_json'] ?? null) }}"
                                                data-item-name="{{ $item->name }} (Baseline)"
                                                @click="$dispatch('open-evidence-modal', {
                                                    imageUrl: $el.dataset.imageUrl,
                                                    annotationJson: JSON.parse($el.dataset.annotationJson || 'null'),
                                                    itemName: $el.dataset.itemName
                                                })"
                                                style="position: relative; flex-shrink: 0; cursor: pointer; border-radius: 0.375rem; overflow: hidden; border: 1px solid #d1d5db;"
                                            >
                                                <img src="{{ $ev['url'] }}" style="height: 100px; width: 140px; object-fit: cover; display: block;">
                                                <span style="position: absolute; bottom: 0.25rem; left: 0.25rem; background: rgba(31, 41, 55, 0.85); color: white; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.6875rem; font-weight: 600;">
                                                    Baseline
                                                </span>
                                                @if(!empty($ev['has_annotations']))
                                                    <span style="position: absolute; top: 0.25rem; right: 0.25rem; background: rgba(79, 70, 229, 0.95); color: white; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.6875rem; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.3);">
                                                        🎨 Annotated
                                                    </span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if($item->evidence->isNotEmpty())
                                <div style="border-top: 1px solid rgba(229, 231, 235, 1); padding-top: 1rem; margin-top: 0.5rem;">
                                    <div style="font-size: 0.875rem; font-weight: 600; color: rgba(55, 65, 81, 1); margin-bottom: 0.75rem;">Current Evidence ({{ $item->evidence->count() }})</div>
                                    <div style="display: flex; gap: 1rem; overflow-x: auto; padding-bottom: 0.5rem;">
                                        @foreach($item->evidence as $ev)
                                            @php
                                                $media = $ev->getFirstMedia('images');
                                                $fullUrl = $media ? $media->getUrl() : null;
                                                $url = $fullUrl ? (parse_url($fullUrl, PHP_URL_PATH) ?: $fullUrl) : null;
                                                $hasAnnotations = !empty($ev->annotation_json['canvas']['objects']);
                                            @endphp
                                            @if($url)
                                                <div 
                                                    x-data
                                                    data-image-url="{{ $url }}"
                                                    data-annotation-json="{{ json_encode($ev->annotation_json ?? null) }}"
                                                    data-item-name="{{ $item->name }}"
                                                    @click="$dispatch('open-evidence-modal', {
                                                        imageUrl: $el.dataset.imageUrl,
                                                        annotationJson: JSON.parse($el.dataset.annotationJson || 'null'),
                                                        itemName: $el.dataset.itemName
                                                    })"
                                                    style="position: relative; flex-shrink: 0; cursor: pointer;"
                                                >
                                                    <img src="{{ $url }}" style="height: 120px; width: 160px; object-fit: cover; border-radius: 0.5rem; border: 1px solid rgba(229, 231, 235, 1);">
                                                    @if($hasAnnotations)
                                                        <span style="position: absolute; top: 0.375rem; right: 0.375rem; background: rgba(79, 70, 229, 0.9); color: white; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.3);">
                                                            🎨 Annotated
                                                        </span>
                                                    @endif
                                                </div>
                                            @endif
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </x-filament::section>
                @endforeach
            @endif
        @endif
    </div>
    @endif

    <!-- Evidence Modal with Fabric Annotation Viewer -->
    <div
        x-data="evidenceReviewModal"
        @open-evidence-modal.window="openModal($event.detail)"
        @open-evidence-review-modal.window="openModal($event.detail)"
        x-show="isOpen"
        x-cloak
        style="position: fixed; inset: 0; z-index: 99999; display: flex; align-items: center; justify-content: center; background-color: rgba(17, 24, 39, 0.85); backdrop-filter: blur(4px); padding: 1.5rem;"
    >
        <style>
            .evidence-modal-canvas-wrapper .canvas-container {
                margin: 0 auto !important;
            }
        </style>
        <div 
            @click.away="closeModal()" 
            style="background: #1f2937; border-radius: 0.75rem; max-width: 94vw; max-height: 92vh; width: 1150px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid #374151; margin: auto;"
        >
            <!-- Modal Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem 1.25rem; background: #111827; border-bottom: 1px solid #374151;">
                <div>
                    <h4 style="margin: 0; font-size: 1.125rem; font-weight: 600; color: white;" x-text="'Evidence: ' + itemName"></h4>
                    <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.25rem;">Viewing annotated inspection photo &amp; remarks</div>
                </div>
                <button 
                    type="button" 
                    @click="closeModal()" 
                    style="background: transparent; border: none; color: #9ca3af; font-size: 1.5rem; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 0.375rem;"
                    onmouseover="this.style.color='white'" 
                    onmouseout="this.style.color='#9ca3af'"
                >&times;</button>
            </div>

            <!-- Modal Workspace (Canvas + Remarks Side Panel) -->
            <div style="flex: 1; display: flex; overflow: hidden; background: #111827; min-height: 480px;">
                <!-- Left: Centered Canvas Area -->
                <div style="flex: 1; overflow: auto; padding: 1.5rem; display: flex; align-items: center; justify-content: center; background: #111827;">
                    <div wire:ignore x-ref="modalCanvasContainer" class="evidence-modal-canvas-wrapper" style="box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); outline: 1px solid rgba(255,255,255,0.1); border-radius: 0.375rem; overflow: hidden; display: flex; align-items: center; justify-content: center; margin: auto;">
                        <canvas id="evidenceModalCanvas"></canvas>
                    </div>
                </div>

                <!-- Right: Annotations & Remarks Side Panel -->
                <div style="width: 320px; background-color: #1f2937; border-left: 1px solid #374151; display: flex; flex-direction: column; flex-shrink: 0;">
                    <div style="padding: 0.875rem 1rem; border-bottom: 1px solid #374151; font-size: 0.75rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; justify-content: space-between;">
                        <span>Annotations &amp; Remarks</span>
                        <span x-text="layers.length" style="background: #374151; color: white; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem;"></span>
                    </div>
                    <div style="flex: 1; overflow-y: auto; padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem;">
                        <template x-if="layers.length === 0">
                            <div style="color: #6b7280; font-size: 0.875rem; text-align: center; padding: 2rem 0;">
                                No annotation remarks recorded for this photo.
                            </div>
                        </template>
                        <template x-for="(layer, index) in layers" :key="layer.id">
                            <div style="padding: 0.75rem; border-radius: 0.5rem; border: 1px solid #374151; background: #111827; display: flex; flex-direction: column; gap: 0.375rem;">
                                <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.875rem; font-weight: 500; color: #d1d5db;">
                                    <span x-text="index + 1" style="width: 1.25rem; height: 1.25rem; display: flex; align-items: center; justify-content: center; background: #374151; border-radius: 0.25rem; font-size: 0.75rem; flex-shrink: 0; color: white;"></span>
                                    <span x-text="layer.type" style="text-transform: capitalize; color: #818cf8; font-weight: 600;"></span>
                                </div>
                                <div 
                                    x-text="layer.remark && layer.remark.trim() !== '' ? layer.remark : '(No remark text)'" 
                                    :style="layer.remark && layer.remark.trim() !== '' ? 'color: #f3f4f6;' : 'color: #6b7280; font-style: italic;'"
                                    style="font-size: 0.875rem; word-break: break-word; line-height: 1.4;"
                                ></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div style="display: flex; align-items: center; justify-content: flex-end; padding: 0.75rem 1.25rem; background: #1f2937; border-top: 1px solid #374151;">
                <button 
                    type="button" 
                    @click="closeModal()" 
                    style="padding: 0.5rem 1.25rem; background: #374151; color: white; border: none; border-radius: 0.375rem; font-size: 0.875rem; font-weight: 500; cursor: pointer;"
                >Close</button>
            </div>
        </div>
    </div>

    <x-filament-actions::modals />
</div>

@script
<script>
    Alpine.data('evidenceReviewModal', () => ({
        isOpen: false,
        imageUrl: '',
        annotationJson: null,
        itemName: '',
        viewer: null,
        layers: [],

        openModal(detail) {
            this.imageUrl = detail.imageUrl;
            this.annotationJson = detail.annotationJson;
            this.itemName = detail.itemName;
            this.layers = [];
            this.isOpen = true;

            if (this.viewer && this.viewer.canvas) {
                try { this.viewer.canvas.dispose(); } catch(e) {}
                this.viewer = null;
            }

            this.$nextTick(() => {
                if (this.$refs.modalCanvasContainer) {
                    this.$refs.modalCanvasContainer.innerHTML = '<canvas id="evidenceModalCanvas"></canvas>';
                }

                setTimeout(() => {
                    const canvasEl = document.getElementById('evidenceModalCanvas');
                    if (window.AnnotationViewer && canvasEl) {
                        this.viewer = new window.AnnotationViewer(
                            canvasEl,
                            this.imageUrl,
                            this.annotationJson,
                            (layers) => {
                                this.layers = layers;
                            }
                        );
                    } else {
                        console.error('AnnotationViewer not found or canvas missing');
                    }
                }, 100);
            });
        },

        closeModal() {
            this.isOpen = false;
            this.imageUrl = '';
            this.annotationJson = null;
            this.layers = [];
            if (this.viewer && this.viewer.canvas) {
                try { this.viewer.canvas.dispose(); } catch(e) {}
                this.viewer = null;
            }
            if (this.$refs.modalCanvasContainer) {
                this.$refs.modalCanvasContainer.innerHTML = '';
            }
        }
    }));
</script>
@endscript
