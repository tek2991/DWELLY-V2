<div style="display: flex; flex-direction: column; gap: 1.5rem; position: relative;">
    <style>
        @media (max-width: 768px) {
            .audit-header-actions-wrapper {
                width: 100% !important;
                margin-top: 0.5rem !important;
                padding-top: 0.625rem !important;
                border-top: 1px solid rgba(229, 231, 235, 1) !important;
            }
            .audit-review-header-actions {
                width: 100% !important;
                display: flex !important;
                flex-direction: column !important;
                gap: 0.625rem !important;
            }
            .audit-review-round-pill {
                align-self: flex-start !important;
            }
            .audit-review-buttons-group {
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 0.5rem !important;
                width: 100% !important;
            }
            .audit-review-buttons-group .audit-btn-full-width {
                grid-column: 1 / -1 !important;
            }
            .audit-review-buttons-group .fi-btn,
            .audit-review-buttons-group button {
                width: 100% !important;
                justify-content: center !important;
                font-size: 0.8125rem !important;
                padding: 0.5rem 0.5rem !important;
                min-height: 2.5rem !important;
                white-space: nowrap !important;
            }
            .audit-review-buttons-group .fi-btn-label {
                overflow: hidden !important;
                text-overflow: ellipsis !important;
                white-space: nowrap !important;
            }
            .audit-review-metrics-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 0.625rem !important;
                margin-top: 0.75rem !important;
            }
            .audit-metric-card {
                padding: 0.75rem 0.625rem !important;
            }
            .audit-metric-card .metric-value {
                font-size: 1.5rem !important;
            }
            .audit-metric-card .metric-label {
                font-size: 0.75rem !important;
            }
            .audit-item-card-row {
                flex-direction: column !important;
                align-items: stretch !important;
                gap: 0.75rem !important;
            }
            .audit-item-actions {
                width: 100% !important;
                display: grid !important;
                grid-template-columns: repeat(2, minmax(0, 1fr)) !important;
                gap: 0.5rem !important;
                margin-top: 0.75rem !important;
                padding-top: 0.75rem !important;
                border-top: 1px solid rgba(229, 231, 235, 1) !important;
            }
            .audit-item-actions button,
            .audit-item-actions .fi-btn {
                width: 100% !important;
                justify-content: center !important;
                font-size: 0.8125rem !important;
                padding: 0.5rem 0.75rem !important;
                min-height: 2.5rem !important;
            }
            .audit-item-actions .audit-btn-full-width {
                grid-column: 1 / -1 !important;
            }
            .audit-item-actions .audit-btn-full-width button,
            .audit-item-actions .audit-btn-full-width .fi-btn {
                width: 100% !important;
            }
            .evidence-modal-backdrop {
                padding: 0.5rem !important;
            }
            .evidence-modal-card {
                max-width: 98vw !important;
                max-height: 96vh !important;
                width: 100% !important;
            }
            .evidence-modal-mobile-tabs {
                display: flex !important;
            }
            .evidence-modal-workspace {
                flex-direction: column !important;
                min-height: auto !important;
                overflow-y: auto !important;
            }
            .evidence-modal-canvas-pane[data-mobile-active="false"] {
                display: none !important;
            }
            .evidence-modal-remarks-pane[data-mobile-active="false"] {
                display: none !important;
            }
            .evidence-modal-canvas-pane[data-mobile-active="true"] {
                display: flex !important;
                flex: 1 1 auto !important;
                padding: 0.75rem !important;
                min-height: 320px !important;
            }
            .evidence-modal-remarks-pane[data-mobile-active="true"] {
                display: flex !important;
                flex: 1 1 auto !important;
                width: 100% !important;
                border-left: none !important;
                border-top: 1px solid #374151 !important;
                min-height: 320px !important;
            }
            .audit-mobile-sticky-bar {
                display: flex !important;
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                z-index: 30 !important;
                background: rgba(255, 255, 255, 0.98) !important;
                backdrop-filter: blur(8px) !important;
                border-top: 1px solid rgba(229, 231, 235, 1) !important;
                padding: 0.625rem 1rem !important;
                box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.08) !important;
                align-items: center !important;
                justify-content: space-between !important;
            }
            .audit-review-bottom-spacer {
                height: 4.5rem !important;
                display: block !important;
            }
        }
        @media (max-width: 420px) {
            .audit-review-buttons-group {
                grid-template-columns: 1fr !important;
            }
        }
        @media (min-width: 769px) {
            .audit-review-header-actions {
                display: flex !important;
                align-items: center !important;
                gap: 0.5rem !important;
                flex-wrap: wrap !important;
            }
            .audit-review-buttons-group {
                display: flex !important;
                align-items: center !important;
                gap: 0.5rem !important;
                flex-wrap: wrap !important;
            }
            .audit-review-buttons-group .audit-btn-wrapper {
                display: inline-flex !important;
            }
            .audit-review-metrics-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr)) !important;
                gap: 1rem !important;
                margin-top: 1rem !important;
            }
            .audit-item-actions {
                display: flex !important;
                align-items: center !important;
                gap: 0.5rem !important;
                flex-shrink: 0 !important;
                flex-wrap: wrap !important;
            }
            .evidence-modal-mobile-tabs {
                display: none !important;
            }
            .evidence-modal-canvas-pane {
                display: flex !important;
                flex: 1 !important;
            }
            .evidence-modal-remarks-pane {
                display: flex !important;
                width: 320px !important;
            }
            .audit-mobile-sticky-bar,
            .audit-review-bottom-spacer {
                display: none !important;
            }
        }
    </style>

    @php
        $totalItems = $audit->items->count();
        $approvedItems = $audit->items->where('status', \App\Domain\Audit\Enums\ItemStatus::APPROVED)->count();
        $rejectedItems = $audit->items->where('status', \App\Domain\Audit\Enums\ItemStatus::REJECTED)->count();
        $pendingItems = $totalItems - $approvedItems - $rejectedItems;
    @endphp

    <!-- Audit Summary -->
    <x-filament::section>
        <div style="display: flex; flex-direction: column; gap: 1.25rem; width: 100%;">
            <x-audit-header :audit="$audit">
                <x-slot name="actions">
                    <div class="audit-review-header-actions">
                        <div class="audit-review-round-pill">
                            <span style="font-size: 0.8125rem; color: #4b5563; background: #f3f4f6; padding: 0.25rem 0.625rem; border-radius: 9999px; font-weight: 500; border: 1px solid #e5e7eb; display: inline-flex; align-items: center; gap: 0.25rem; white-space: nowrap;">
                                Review Round: <strong>{{ $audit->review_round }}</strong>
                            </span>
                        </div>

                        @if($audit->canReview() || $audit->canReopen())
                            <div class="audit-review-buttons-group">
                                @if($audit->canReview())
                                    <div class="audit-btn-wrapper">
                                        {{ $this->acceptAllAction }}
                                    </div>
                                    <div class="audit-btn-wrapper">
                                        {{ $this->approveAuditAction }}
                                    </div>
                                    <div class="audit-btn-wrapper audit-btn-full-width">
                                        {{ $this->requestChangesAction }}
                                    </div>
                                @endif
                                @if($audit->canReopen())
                                    <div class="audit-btn-wrapper audit-btn-full-width">
                                        {{ $this->reopenAuditAction }}
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </x-slot>
            </x-audit-header>

            <!-- Responsive, Interactive Summary Metric Cards -->
            <div class="audit-review-metrics-grid" style="display: grid; gap: 1rem;">
            <div 
                wire:click="setItemFilter('all')"
                class="audit-metric-card"
                style="background: {{ $itemFilter === 'all' ? '#e5e7eb' : '#f3f4f6' }}; padding: 1rem; border-radius: 0.5rem; text-align: center; cursor: pointer; border: 2px solid {{ $itemFilter === 'all' ? '#4f46e5' : 'transparent' }}; transition: all 0.15s ease;"
                title="Click to show all items"
            >
                <div class="metric-value" style="font-size: 2rem; font-weight: 700; color: rgba(17, 24, 39, 1);">{{ $totalItems }}</div>
                <div class="metric-label" style="font-size: 0.8125rem; font-weight: 600; color: rgba(107, 114, 128, 1); text-transform: uppercase;">Total Items</div>
            </div>
            
            <div 
                wire:click="setItemFilter('approved')"
                class="audit-metric-card"
                style="background: {{ $itemFilter === 'approved' ? '#bbf7d0' : '#dcfce7' }}; padding: 1rem; border-radius: 0.5rem; text-align: center; cursor: pointer; border: 2px solid {{ $itemFilter === 'approved' ? '#16a34a' : 'transparent' }}; transition: all 0.15s ease;"
                title="Click to show approved items"
            >
                <div class="metric-value" style="font-size: 2rem; font-weight: 700; color: rgba(21, 128, 61, 1);">{{ $approvedItems }}</div>
                <div class="metric-label" style="font-size: 0.8125rem; font-weight: 600; color: rgba(22, 163, 74, 1); text-transform: uppercase;">Approved</div>
            </div>
            
            <div 
                wire:click="setItemFilter('rejected')"
                class="audit-metric-card"
                style="background: {{ $itemFilter === 'rejected' ? '#fecaca' : '#fee2e2' }}; padding: 1rem; border-radius: 0.5rem; text-align: center; cursor: pointer; border: 2px solid {{ $itemFilter === 'rejected' ? '#dc2626' : 'transparent' }}; transition: all 0.15s ease;"
                title="Click to show rejected items"
            >
                <div class="metric-value" style="font-size: 2rem; font-weight: 700; color: rgba(220, 38, 38, 1);">{{ $rejectedItems }}</div>
                <div class="metric-label" style="font-size: 0.8125rem; font-weight: 600; color: rgba(239, 68, 68, 1); text-transform: uppercase;">Rejected</div>
            </div>
            
            <div 
                wire:click="setItemFilter('pending')"
                class="audit-metric-card"
                style="background: {{ $itemFilter === 'pending' ? '#fde68a' : '#fef3c7' }}; padding: 1rem; border-radius: 0.5rem; text-align: center; cursor: pointer; border: 2px solid {{ $itemFilter === 'pending' ? '#d97706' : 'transparent' }}; transition: all 0.15s ease;"
                title="Click to show pending items"
            >
                <div class="metric-value" style="font-size: 2rem; font-weight: 700; color: rgba(202, 138, 4, 1);">{{ $pendingItems }}</div>
                <div class="metric-label" style="font-size: 0.8125rem; font-weight: 600; color: rgba(217, 119, 6, 1); text-transform: uppercase;">Pending</div>
            </div>
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
                            <div style="font-size: 0.8125rem; color: #78350f; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
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
                                    <x-filament::icon icon="heroicon-o-video-camera" style="width: 1.25rem; height: 1.25rem; color: #4f46e5;" />
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
                            <div style="font-size: 0.8125rem; color: rgba(107, 114, 128, 1); display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
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
                    $catItems = $activeCategory ? $activeCategory->items : collect();
                    $catTotalCount = $catItems->count();
                    $catApprovedCount = $catItems->where('status', \App\Domain\Audit\Enums\ItemStatus::APPROVED)->count();
                    $catRejectedCount = $catItems->where('status', \App\Domain\Audit\Enums\ItemStatus::REJECTED)->count();
                    $catPendingCount = $catTotalCount - $catApprovedCount - $catRejectedCount;

                    $filteredItems = match($itemFilter) {
                        'approved' => $catItems->where('status', \App\Domain\Audit\Enums\ItemStatus::APPROVED),
                        'rejected' => $catItems->where('status', \App\Domain\Audit\Enums\ItemStatus::REJECTED),
                        'pending' => $catItems->whereNotIn('status', [\App\Domain\Audit\Enums\ItemStatus::APPROVED, \App\Domain\Audit\Enums\ItemStatus::REJECTED]),
                        default => $catItems,
                    };
                @endphp

                @if($catTotalCount > 0)
                    <!-- Quick Category Filter Chips -->
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.25rem;">
                        <div style="display: flex; align-items: center; gap: 0.375rem; overflow-x: auto; padding: 0.25rem 0; -webkit-overflow-scrolling: touch;">
                            <button
                                type="button"
                                wire:click="setItemFilter('all')"
                                style="padding: 0.3125rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: 1px solid {{ $itemFilter === 'all' ? '#4f46e5' : '#e5e7eb' }}; background: {{ $itemFilter === 'all' ? '#eef2ff' : 'white' }}; color: {{ $itemFilter === 'all' ? '#4f46e5' : '#4b5563' }}; white-space: nowrap;"
                            >
                                All ({{ $catTotalCount }})
                            </button>
                            <button
                                type="button"
                                wire:click="setItemFilter('pending')"
                                style="padding: 0.3125rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: 1px solid {{ $itemFilter === 'pending' ? '#d97706' : '#e5e7eb' }}; background: {{ $itemFilter === 'pending' ? '#fef3c7' : 'white' }}; color: {{ $itemFilter === 'pending' ? '#b45309' : '#4b5563' }}; white-space: nowrap;"
                            >
                                ⏳ Pending ({{ $catPendingCount }})
                            </button>
                            <button
                                type="button"
                                wire:click="setItemFilter('approved')"
                                style="padding: 0.3125rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: 1px solid {{ $itemFilter === 'approved' ? '#16a34a' : '#e5e7eb' }}; background: {{ $itemFilter === 'approved' ? '#dcfce7' : 'white' }}; color: {{ $itemFilter === 'approved' ? '#15803d' : '#4b5563' }}; white-space: nowrap;"
                            >
                                ✓ Approved ({{ $catApprovedCount }})
                            </button>
                            @if($catRejectedCount > 0)
                                <button
                                    type="button"
                                    wire:click="setItemFilter('rejected')"
                                    style="padding: 0.3125rem 0.625rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; cursor: pointer; border: 1px solid {{ $itemFilter === 'rejected' ? '#dc2626' : '#e5e7eb' }}; background: {{ $itemFilter === 'rejected' ? '#fee2e2' : 'white' }}; color: {{ $itemFilter === 'rejected' ? '#b91c1c' : '#4b5563' }}; white-space: nowrap;"
                                >
                                    ✕ Rejected ({{ $catRejectedCount }})
                                </button>
                            @endif
                        </div>
                        <div style="font-size: 0.75rem; color: #6b7280; white-space: nowrap;">
                            Showing {{ $filteredItems->count() }} of {{ $catTotalCount }}
                        </div>
                    </div>
                @endif
            
                @if($filteredItems->isNotEmpty())
                    @foreach($filteredItems as $item)
                        <x-filament::section compact>
                            <div style="display: flex; flex-direction: column; gap: 1rem; width: 100%;">
                                
                                <div class="audit-item-card-row" style="display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem;">
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

                                        <div style="font-size: 0.8125rem; color: #6b7280; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; margin-top: 0.125rem;">
                                            <span>{{ $item->snapshot_data['display_name'] ?? ($item->snapshot_data['brand'] ?? 'Item Details') }}</span>

                                            @if(!empty($refData['condition']))
                                                <span>&bull;</span>
                                                <span style="font-size: 0.75rem; font-weight: 600; color: #92400e; background: #fef3c7; border: 1px solid #fde68a; padding: 0.125rem 0.375rem; border-radius: 0.25rem; display: inline-flex; align-items: center; gap: 0.25rem;">
                                                    Baseline: <strong>{{ $refData['condition'] }}</strong>
                                                </span>
                                            @endif

                                            @if($item->condition)
                                                @if(!empty($refData['condition']))
                                                    <span style="color: #9ca3af; font-weight: 700;">&rarr;</span>
                                                @else
                                                    <span>&bull;</span>
                                                @endif
                                                <span style="font-size: 0.75rem; font-weight: 600; color: #374151; display: inline-flex; align-items: center; gap: 0.25rem;">
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

                                    <!-- Responsive Action Buttons (Inline on Desktop, Thumb-Grid on Mobile) -->
                                    <div class="audit-item-actions">
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
                                                <div class="audit-btn-full-width">
                                                    {{ ($this->toggleExcludeFromSyncAction)(['item_id' => $item->id]) }}
                                                </div>
                                            @endif
                                        @endif
                                        @if($item->isApproved() && empty($item->source_id))
                                            <div class="audit-btn-full-width">
                                                {{ ($this->syncToPropertyAction)(['item_id' => $item->id]) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                @if(!empty($refData['evidence']) && count($refData['evidence']) > 0)
                                    <div style="border-top: 1px dashed rgba(209, 213, 219, 1); padding-top: 0.875rem; margin-top: 0.5rem;">
                                        <div style="font-size: 0.875rem; font-weight: 600; color: #d97706; margin-bottom: 0.5rem; display: flex; align-items: center; gap: 0.375rem;">
                                            <x-filament::icon icon="heroicon-o-document-duplicate" style="width: 1rem; height: 1rem; color: #d97706;" />
                                            <span>Reference Baseline Photos ({{ count($refData['evidence']) }})</span>
                                        </div>
                                        <div style="display: flex; gap: 0.75rem; overflow-x: auto; padding-bottom: 0.375rem; -webkit-overflow-scrolling: touch;">
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
                                    <div style="border-top: 1px solid rgba(229, 231, 235, 1); padding-top: 0.875rem; margin-top: 0.5rem;">
                                        <div style="font-size: 0.875rem; font-weight: 600; color: rgba(55, 65, 81, 1); margin-bottom: 0.5rem;">Current Evidence ({{ $item->evidence->count() }})</div>
                                        <div style="display: flex; gap: 0.75rem; overflow-x: auto; padding-bottom: 0.5rem; -webkit-overflow-scrolling: touch;">
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
                                                        <img src="{{ $url }}" style="height: 110px; width: 145px; object-fit: cover; border-radius: 0.5rem; border: 1px solid rgba(229, 231, 235, 1);">
                                                        @if($hasAnnotations)
                                                            <span style="position: absolute; top: 0.375rem; right: 0.375rem; background: rgba(79, 70, 229, 0.9); color: white; padding: 0.125rem 0.375rem; border-radius: 0.25rem; font-size: 0.6875rem; font-weight: 600; box-shadow: 0 1px 3px rgba(0,0,0,0.3);">
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
                @else
                    <div style="text-align: center; padding: 2rem 1rem; color: #6b7280; background: #f9fafb; border-radius: 0.5rem; border: 1px dashed #d1d5db; margin-top: 0.5rem;">
                        <p style="margin: 0; font-size: 0.875rem; font-weight: 500;">No {{ $itemFilter !== 'all' ? $itemFilter : '' }} items found in {{ $activeCategory->name }}.</p>
                        @if($itemFilter !== 'all')
                            <button type="button" wire:click="setItemFilter('all')" style="margin-top: 0.5rem; background: transparent; border: none; color: #4f46e5; font-size: 0.8125rem; font-weight: 600; cursor: pointer; text-decoration: underline;">
                                Show all items in this category
                            </button>
                        @endif
                    </div>
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
        class="evidence-modal-backdrop"
        style="position: fixed; inset: 0; z-index: 99999; display: flex; align-items: center; justify-content: center; background-color: rgba(17, 24, 39, 0.85); backdrop-filter: blur(4px); padding: 1.5rem;"
    >
        <style>
            .evidence-modal-canvas-wrapper .canvas-container {
                margin: 0 auto !important;
            }
        </style>
        <div 
            @click.away="closeModal()" 
            class="evidence-modal-card"
            style="background: #1f2937; border-radius: 0.75rem; max-width: 94vw; max-height: 92vh; width: 1150px; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid #374151; margin: auto;"
        >
            <!-- Modal Header -->
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1.25rem; background: #111827; border-bottom: 1px solid #374151;">
                <div>
                    <h4 style="margin: 0; font-size: 1.05rem; font-weight: 600; color: white;" x-text="'Evidence: ' + itemName"></h4>
                    <div style="font-size: 0.75rem; color: #9ca3af; margin-top: 0.2rem;">Viewing annotated inspection photo &amp; remarks</div>
                </div>

                <div style="display: flex; align-items: center; gap: 0.75rem;">
                    <!-- Mobile Tab Toggle (Canvas vs Remarks) -->
                    <div class="evidence-modal-mobile-tabs" style="align-items: center; gap: 0.25rem; background: #374151; padding: 0.125rem; border-radius: 0.375rem;">
                        <button 
                            type="button" 
                            @click="activeMobileTab = 'canvas'"
                            :style="activeMobileTab === 'canvas' ? 'background: #4f46e5; color: white;' : 'background: transparent; color: #9ca3af;'"
                            style="border: none; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; cursor: pointer;"
                        >
                            Canvas
                        </button>
                        <button 
                            type="button" 
                            @click="activeMobileTab = 'remarks'"
                            :style="activeMobileTab === 'remarks' ? 'background: #4f46e5; color: white;' : 'background: transparent; color: #9ca3af;'"
                            style="border: none; padding: 0.25rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; font-weight: 600; cursor: pointer;"
                        >
                            Remarks (<span x-text="layers.length"></span>)
                        </button>
                    </div>

                    <button 
                        type="button" 
                        @click="closeModal()" 
                        style="background: transparent; border: none; color: #9ca3af; font-size: 1.5rem; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 0.375rem;"
                        onmouseover="this.style.color='white'" 
                        onmouseout="this.style.color='#9ca3af'"
                    >&times;</button>
                </div>
            </div>

            <!-- Modal Workspace (Canvas + Remarks Side Panel) -->
            <div class="evidence-modal-workspace" style="flex: 1; display: flex; overflow: hidden; background: #111827; min-height: 480px;">
                <!-- Left: Centered Canvas Area -->
                <div class="evidence-modal-canvas-pane" :data-mobile-active="activeMobileTab === 'canvas'" style="flex: 1; overflow: auto; padding: 1.5rem; display: flex; align-items: center; justify-content: center; background: #111827;">
                    <div wire:ignore x-ref="modalCanvasContainer" class="evidence-modal-canvas-wrapper" style="box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); outline: 1px solid rgba(255,255,255,0.1); border-radius: 0.375rem; overflow: hidden; display: flex; align-items: center; justify-content: center; margin: auto;">
                        <canvas id="evidenceModalCanvas"></canvas>
                    </div>
                </div>

                <!-- Right: Annotations & Remarks Side Panel -->
                <div class="evidence-modal-remarks-pane" :data-mobile-active="activeMobileTab === 'remarks'" style="width: 320px; background-color: #1f2937; border-left: 1px solid #374151; display: flex; flex-direction: column; flex-shrink: 0;">
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

    <!-- Mobile Sticky Review Action / Progress Bar -->
    <div class="audit-mobile-sticky-bar">
        <div style="display: flex; flex-direction: column; gap: 0.125rem;">
            <div style="font-size: 0.75rem; font-weight: 700; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em;">
                {{ $approvedItems }}/{{ $totalItems }} Approved ({{ $totalItems > 0 ? round(($approvedItems / $totalItems) * 100) : 0 }}%)
            </div>
            <div style="font-size: 0.8125rem; font-weight: 600;">
                @if($audit->canApprove())
                    <span style="color: #16a34a; display: flex; align-items: center; gap: 0.25rem;">
                        <span>✓ Ready to finalize</span>
                    </span>
                @elseif($rejectedItems > 0)
                    <span style="color: #dc2626;">{{ $rejectedItems }} item(s) rejected</span>
                @else
                    <span style="color: #d97706;">{{ $pendingItems }} item(s) pending</span>
                @endif
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
            @if($audit->canReview())
                @if($audit->canApprove())
                    <button 
                        type="button" 
                        wire:click="mountAction('approveAudit')" 
                        style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.875rem; font-size: 0.8125rem; font-weight: 600; color: white; background-color: #16a34a; border-radius: 0.375rem; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1); cursor: pointer;"
                    >
                        <x-filament::icon icon="heroicon-o-check-circle" style="width: 1rem; height: 1rem;" />
                        <span>Approve Audit</span>
                    </button>
                @elseif($rejectedItems > 0 && $audit->canRequestChanges())
                    <button 
                        type="button" 
                        wire:click="mountAction('requestChanges')" 
                        style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.875rem; font-size: 0.8125rem; font-weight: 600; color: white; background-color: #dc2626; border-radius: 0.375rem; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1); cursor: pointer;"
                    >
                        <x-filament::icon icon="heroicon-o-arrow-uturn-left" style="width: 1rem; height: 1rem;" />
                        <span>Send Back</span>
                    </button>
                @else
                    <button 
                        type="button" 
                        wire:click="mountAction('acceptAll')" 
                        style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.875rem; font-size: 0.8125rem; font-weight: 600; color: white; background-color: #16a34a; border-radius: 0.375rem; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1); cursor: pointer;"
                    >
                        <x-filament::icon icon="heroicon-o-check-badge" style="width: 1rem; height: 1rem;" />
                        <span>Accept All</span>
                    </button>
                @endif
            @elseif($audit->canReopen())
                <button 
                    type="button" 
                    wire:click="mountAction('reopenAudit')" 
                    style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.875rem; font-size: 0.8125rem; font-weight: 600; color: #1f2937; background-color: #f59e0b; border-radius: 0.375rem; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1); cursor: pointer;"
                >
                    <x-filament::icon icon="heroicon-o-arrow-path" style="width: 1rem; height: 1rem;" />
                    <span>Reopen</span>
                </button>
            @endif
        </div>
    </div>

    <div class="audit-review-bottom-spacer"></div>

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
        activeMobileTab: 'canvas',

        openModal(detail) {
            this.imageUrl = detail.imageUrl;
            this.annotationJson = detail.annotationJson;
            this.itemName = detail.itemName;
            this.layers = [];
            this.activeMobileTab = 'canvas';
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
            this.activeMobileTab = 'canvas';
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
