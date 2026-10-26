<div @mount-edit-item.window="$wire.mountEditItem($event.detail.itemId)" style="display: flex; flex-direction: column; gap: 1.5rem; position: relative;">
    <style>
        @media (max-width: 768px) {
            .fi-modal-footer {
                position: sticky !important;
                bottom: 0 !important;
                background: white !important;
                z-index: 30 !important;
                border-top: 1px solid rgba(229, 231, 235, 1) !important;
                padding-top: 0.75rem !important;
                padding-bottom: 0.75rem !important;
                box-shadow: 0 -4px 6px -1px rgba(0, 0, 0, 0.05) !important;
            }
            .fi-modal-footer-actions {
                display: flex !important;
                width: 100% !important;
                align-items: center !important;
                justify-content: space-between !important;
                gap: 0.5rem !important;
                flex-wrap: nowrap !important;
            }
            .fi-modal-footer-actions .audit-btn-prev {
                order: 1 !important;
                flex: 0 0 auto !important;
                padding-left: 0.625rem !important;
                padding-right: 0.625rem !important;
            }
            .fi-modal-footer-actions .audit-btn-save {
                order: 2 !important;
                flex: 0 0 auto !important;
                padding-left: 0.75rem !important;
                padding-right: 0.75rem !important;
            }
            .fi-modal-footer-actions .audit-btn-next {
                order: 3 !important;
                flex: 1 1 auto !important;
                justify-content: center !important;
                font-weight: 700 !important;
                box-shadow: 0 1px 3px rgba(79, 70, 229, 0.3) !important;
            }
            .fi-modal-footer-actions .audit-btn-cancel {
                display: none !important;
            }
            .audit-mobile-fab {
                display: flex !important;
                position: fixed !important;
                bottom: 4.5rem !important;
                right: 1.25rem !important;
                z-index: 35 !important;
            }
            .audit-mobile-sticky-bar {
                display: flex !important;
                position: fixed !important;
                bottom: 0 !important;
                left: 0 !important;
                right: 0 !important;
                z-index: 30 !important;
                background: rgba(255, 255, 255, 0.97) !important;
                backdrop-filter: blur(8px) !important;
                border-top: 1px solid rgba(229, 231, 235, 1) !important;
                padding: 0.625rem 1rem !important;
                box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.08) !important;
                align-items: center !important;
                justify-content: space-between !important;
            }
        }
        @media (min-width: 769px) {
            .audit-mobile-fab, .audit-mobile-sticky-bar {
                display: none !important;
            }
        }
    </style>
    @php
        $totalItems = $audit->items->count();
        $pendingItems = $audit->items->where('status', \App\Domain\Audit\Enums\ItemStatus::PENDING)->count();
        $inspectedItems = $totalItems - $pendingItems;
        $requiresVideo = $audit->audit_type !== \App\Domain\Audit\Enums\AuditType::MAINTENANCE;
        $hasVideo = !$requiresVideo || ($audit->getFirstMedia('layout_video') !== null);
        
        $totalRequirements = $totalItems + ($requiresVideo ? 1 : 0);
        $completedRequirements = $inspectedItems + (($requiresVideo && $audit->getFirstMedia('layout_video') !== null) ? 1 : 0);
        $progress = $totalRequirements > 0 ? round(($completedRequirements / $totalRequirements) * 100) : 0;
    @endphp

    <!-- Unified Audit Header & Control Card -->
    <x-filament::section>
        <div style="display: flex; flex-direction: column; gap: 1.25rem; width: 100%;">
            
            <!-- Row 1: Audit Info & Primary Action Buttons at Top Right -->
            <x-audit-header :audit="$audit">
                <x-slot name="actions">
                    @if($audit->status === \App\Domain\Audit\Enums\AuditStatus::DRAFT)
                        {{ $this->startAuditAction }}
                    @elseif($audit->canSubmit())
                        @if($progress === 100)
                            {{ $this->submitForReviewAction }}
                        @else
                            <div class="hidden sm:inline-flex">
                                {{ $this->submitForReviewAction }}
                            </div>
                        @endif
                    @endif
                </x-slot>
            </x-audit-header>

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
                @if(($requiresVideo && !$hasVideo) || $pendingItems > 0)
                    <div style="background-color: #fffbeb; border: 1px solid #fde68a; color: #92400e; padding: 0.5rem 0.75rem; border-radius: 0.375rem; font-size: 0.8125rem; display: flex; align-items: center; gap: 0.5rem;">
                        <x-filament::icon icon="heroicon-m-information-circle" style="width: 1.125rem; height: 1.125rem; flex-shrink: 0; color: #d97706;" />
                        <span style="line-height: 1.35;">
                            @if($requiresVideo && !$hasVideo && $pendingItems > 0)
                                <strong>Property layout video</strong> required &bull; <strong>{{ $pendingItems }}</strong> item(s) pending inspection.
                            @elseif($requiresVideo && !$hasVideo)
                                <strong>Property layout video</strong> must be uploaded before submitting.
                            @else
                                <strong>{{ $pendingItems }}</strong> item(s) pending inspection before submitting.
                            @endif
                        </span>
                    </div>
                @else
                    <div style="background-color: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 0.625rem 0.875rem; border-radius: 0.375rem; font-size: 0.8125rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <x-filament::icon icon="heroicon-o-check-circle" style="width: 1.125rem; height: 1.125rem; flex-shrink: 0; color: #16a34a;" />
                            <span><strong>All tasks completed!</strong> Ready to submit for review.</span>
                        </div>
                        <div class="sm:hidden">
                            <button
                                type="button"
                                wire:click="mountAction('submitForReview')"
                                style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.35rem 0.75rem; font-size: 0.8125rem; font-weight: 600; color: white; background-color: #16a34a; border-radius: 0.375rem; border: none; cursor: pointer;"
                            >
                                Submit Now
                            </button>
                        </div>
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
                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem; font-weight: 600; color: #374151; margin-bottom: 0.375rem;">
                    <span>Inspection Progress</span>
                    <span style="color: #4b5563;">{{ $completedRequirements }} / {{ $totalRequirements }} Tasks ({{ $progress }}%)</span>
                </div>
                <div style="width: 100%; background-color: rgba(229, 231, 235, 1); border-radius: 9999px; height: 0.45rem; overflow: hidden;">
                    <div style="background-color: #16a34a; height: 100%; border-radius: 9999px; transition: all 0.5s; width: {{ $progress }}%;"></div>
                </div>
            </div>

        </div>
    </x-filament::section>

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
                        if ($audit->video_status === 'rejected') {
                            $videoBadgeText = 'Rejected';
                            $videoBadgeColor = 'danger';
                        } else {
                            $videoBadgeText = '1 / 1';
                            $videoBadgeColor = 'success';
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
                @php
                    $catTotal = $category->items->count();
                    $catInspected = $category->items->where('status', '!=', \App\Domain\Audit\Enums\ItemStatus::PENDING)->count();
                    $isCategoryComplete = $catTotal > 0 && ($catInspected === $catTotal);
                @endphp
                <x-filament::tabs.item
                    :active="$activeCategoryId === $category->id"
                    wire:click="setActiveCategory('{{ $category->id }}')"
                    :icon="$isCategoryComplete ? 'heroicon-m-check-circle' : null"
                >
                    {{ $category->name }}
                    <x-slot name="badge" :color="$isCategoryComplete ? 'success' : 'gray'">
                        {{ $catInspected }} / {{ $catTotal }}
                    </x-slot>
                </x-filament::tabs.item>
            @endforeach
        </x-filament::tabs>

        <!-- Active Tab Content -->
        <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1.5rem;">
            @if($activeCategoryId === 'layout_video')
                @php
                    $refAuditForVideo = $audit->reference_audit_id ? \App\Domain\Audit\Models\Audit::find($audit->reference_audit_id) : null;
                    $refVideoMedia = $refAuditForVideo?->getFirstMedia('layout_video');
                    $refVideoUrl = $refVideoMedia?->getUrl();
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
                        @if($audit->video_status === 'rejected')
                            <div style="font-size: 0.875rem; color: #991b1b; background: #fef2f2; padding: 0.75rem 1rem; border-radius: 0.375rem; border: 1px solid #fecaca; display: flex; flex-direction: column; gap: 0.25rem;">
                                <div style="font-weight: 600; display: flex; align-items: center; gap: 0.375rem;">
                                    <x-filament::icon icon="heroicon-o-exclamation-triangle" style="width: 1rem; height: 1rem; color: #dc2626;" />
                                    <span>Reviewer Rejected Layout Video ({{ $audit->video_rejection_type ?? 'Issue' }}):</span>
                                </div>
                                <div style="color: #7f1d1d; line-height: 1.4;">
                                    {{ $audit->video_rejection_reason }}
                                </div>
                                <div style="font-size: 0.75rem; color: #b91c1c; margin-top: 0.25rem; font-weight: 500;">
                                    Please replace the property layout video with a revised recording before resubmitting the audit.
                                </div>
                            </div>
                        @endif

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
                    $catItems = $activeCategory ? $activeCategory->items : collect();
                    $catTotalCount = $catItems->count();
                    $catPendingCount = $catItems->where('status', \App\Domain\Audit\Enums\ItemStatus::PENDING)->count();
                    $catInspectedCount = $catItems->where('status', \App\Domain\Audit\Enums\ItemStatus::INSPECTED)->count();
                    $catRejectedCount = $catItems->where('status', \App\Domain\Audit\Enums\ItemStatus::REJECTED)->count();

                    $filteredItems = match($itemFilter) {
                        'pending' => $catItems->where('status', \App\Domain\Audit\Enums\ItemStatus::PENDING),
                        'inspected' => $catItems->where('status', \App\Domain\Audit\Enums\ItemStatus::INSPECTED),
                        'rejected' => $catItems->where('status', \App\Domain\Audit\Enums\ItemStatus::REJECTED),
                        default => $catItems,
                    };
                @endphp

            @if($catTotalCount > 0)
                <!-- Quick Filter Chips for Mobile / Desktop -->
                <div style="display: flex; align-items: center; gap: 0.5rem; overflow-x: auto; padding: 0.25rem 0; -webkit-overflow-scrolling: touch; scrollbar-width: none;">
                    <button
                        type="button"
                        wire:click="setItemFilter('all')"
                        style="padding: 0.35rem 0.75rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: 1px solid {{ $itemFilter === 'all' ? '#4f46e5' : '#e5e7eb' }}; background: {{ $itemFilter === 'all' ? '#eef2ff' : 'white' }}; color: {{ $itemFilter === 'all' ? '#4f46e5' : '#4b5563' }}; white-space: nowrap;"
                    >
                        All ({{ $catTotalCount }})
                    </button>
                    <button
                        type="button"
                        wire:click="setItemFilter('pending')"
                        style="padding: 0.35rem 0.75rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: 1px solid {{ $itemFilter === 'pending' ? '#f59e0b' : '#e5e7eb' }}; background: {{ $itemFilter === 'pending' ? '#fffbeb' : 'white' }}; color: {{ $itemFilter === 'pending' ? '#b45309' : '#4b5563' }}; white-space: nowrap;"
                    >
                        ⏳ Pending ({{ $catPendingCount }})
                    </button>
                    @if($catRejectedCount > 0)
                        <button
                            type="button"
                            wire:click="setItemFilter('rejected')"
                            style="padding: 0.35rem 0.75rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: 1px solid {{ $itemFilter === 'rejected' ? '#ef4444' : '#e5e7eb' }}; background: {{ $itemFilter === 'rejected' ? '#fef2f2' : 'white' }}; color: {{ $itemFilter === 'rejected' ? '#dc2626' : '#4b5563' }}; white-space: nowrap;"
                        >
                            ⚠️ Issues ({{ $catRejectedCount }})
                        </button>
                    @endif
                    <button
                        type="button"
                        wire:click="setItemFilter('inspected')"
                        style="padding: 0.35rem 0.75rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 600; cursor: pointer; border: 1px solid {{ $itemFilter === 'inspected' ? '#22c55e' : '#e5e7eb' }}; background: {{ $itemFilter === 'inspected' ? '#f0fdf4' : 'white' }}; color: {{ $itemFilter === 'inspected' ? '#16a34a' : '#4b5563' }}; white-space: nowrap;"
                    >
                        ✅ Completed ({{ $catInspectedCount }})
                    </button>
                </div>
            @endif
            
            @if($catItems->isNotEmpty())
                @if($filteredItems->isEmpty())
                    <div style="text-align: center; padding: 2rem 1rem; color: rgba(107, 114, 128, 1); background: rgba(249, 250, 251, 1); border-radius: 0.5rem; border: 1px dashed rgba(209, 213, 219, 1);">
                        No items match the <strong>{{ ucfirst($itemFilter) }}</strong> filter.
                        <button type="button" wire:click="setItemFilter('all')" style="color: #4f46e5; text-decoration: underline; background: none; border: none; cursor: pointer; margin-left: 0.25rem;">View All Items</button>
                    </div>
                @else
                    @foreach($filteredItems as $item)
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
                                        @php
                                            $refKey = ($item->source_type && $item->source_id)
                                                ? ($item->source_type . '_' . $item->source_id)
                                                : ('name_' . mb_strtolower(trim($item->name)));
                                            $refData = $referenceItems[$refKey] ?? null;
                                        @endphp

                                        <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); margin-top: 0.25rem; display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
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
                                        </div>

                                        @if(!empty($refData['remarks']))
                                            <div style="font-size: 0.8125rem; color: #374151; background: #f3f4f6; padding: 0.375rem 0.625rem; border-radius: 0.375rem; border-left: 3px solid #9ca3af; margin-top: 0.375rem;">
                                                <strong>Previous Remarks:</strong> {{ $refData['remarks'] }}
                                            </div>
                                        @endif

                                        @if($item->remarks)
                                            <div style="font-size: 0.8125rem; color: #1e40af; background: #eff6ff; padding: 0.375rem 0.625rem; border-radius: 0.375rem; border-left: 3px solid #3b82f6; margin-top: 0.375rem;">
                                                <strong>Fresh Remarks:</strong> {{ $item->remarks }}
                                            </div>
                                        @endif


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
                @endif
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

        @if($this->isAuditEditable())
            <!-- Mobile Floating Action Button (FAB) -->
            <div class="audit-mobile-fab">
                @if($activeCategoryName === 'rooms')
                    <button type="button" wire:click="mountAction('createRoom')" style="display: flex; align-items: center; justify-content: center; width: 3.5rem; height: 3.5rem; border-radius: 9999px; background-color: #4f46e5; color: white; border: none; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4); cursor: pointer;" title="Add Room">
                        <x-filament::icon icon="heroicon-o-plus" style="width: 1.75rem; height: 1.75rem;" />
                    </button>
                @elseif($activeCategoryName === 'inventory')
                    <button type="button" wire:click="mountAction('createInventory')" style="display: flex; align-items: center; justify-content: center; width: 3.5rem; height: 3.5rem; border-radius: 9999px; background-color: #4f46e5; color: white; border: none; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4); cursor: pointer;" title="Add Inventory Item">
                        <x-filament::icon icon="heroicon-o-plus" style="width: 1.75rem; height: 1.75rem;" />
                    </button>
                @elseif($activeCategoryName === 'utilities')
                    <button type="button" wire:click="mountAction('createUtility')" style="display: flex; align-items: center; justify-content: center; width: 3.5rem; height: 3.5rem; border-radius: 9999px; background-color: #4f46e5; color: white; border: none; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4); cursor: pointer;" title="Add Utility">
                        <x-filament::icon icon="heroicon-o-plus" style="width: 1.75rem; height: 1.75rem;" />
                    </button>
                @else
                    <button type="button" wire:click="mountAction('createItem')" style="display: flex; align-items: center; justify-content: center; width: 3.5rem; height: 3.5rem; border-radius: 9999px; background-color: #4f46e5; color: white; border: none; box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4); cursor: pointer;" title="Add Found Item">
                        <x-filament::icon icon="heroicon-o-plus" style="width: 1.75rem; height: 1.75rem;" />
                    </button>
                @endif
            </div>
        @endif
    @endif
    @endif

    <!-- Evidence Modal with Fabric Annotation Viewer -->
    <div
        x-data="evidenceReviewModal"
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
            @media (max-width: 767px) {
                .evidence-modal-backdrop {
                    padding: 0.5rem !important;
                }
                .evidence-modal-card {
                    max-width: 98vw !important;
                    max-height: 95vh !important;
                    width: 100% !important;
                }
                .evidence-modal-workspace {
                    flex-direction: column !important;
                    min-height: auto !important;
                    overflow-y: auto !important;
                }
                .evidence-modal-canvas-pane {
                    padding: 0.5rem !important;
                    flex: none !important;
                }
                .evidence-modal-remarks-pane {
                    width: 100% !important;
                    border-left: none !important;
                    border-top: 1px solid #374151 !important;
                    max-height: 220px !important;
                    flex: none !important;
                }
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
                <button 
                    type="button" 
                    @click="closeModal()" 
                    style="background: transparent; border: none; color: #9ca3af; font-size: 1.5rem; cursor: pointer; padding: 0.25rem 0.5rem; border-radius: 0.375rem;"
                    onmouseover="this.style.color='white'" 
                    onmouseout="this.style.color='#9ca3af'"
                >&times;</button>
            </div>

            <!-- Modal Workspace (Canvas + Remarks Side Panel) -->
            <div class="evidence-modal-workspace" style="flex: 1; display: flex; overflow: hidden; background: #111827; min-height: 480px;">
                <!-- Left: Centered Canvas Area -->
                <div class="evidence-modal-canvas-pane" style="flex: 1; overflow: auto; padding: 1.5rem; display: flex; align-items: center; justify-content: center; background: #111827; touch-action: none;">
                    <div wire:ignore x-ref="modalCanvasContainer" class="evidence-modal-canvas-wrapper" style="box-shadow: 0 20px 25px -5px rgba(0,0,0,0.5); outline: 1px solid rgba(255,255,255,0.1); border-radius: 0.375rem; overflow: hidden; display: flex; align-items: center; justify-content: center; margin: auto;">
                        <canvas id="evidenceModalCanvas"></canvas>
                    </div>
                </div>

                <!-- Right: Annotations & Remarks Side Panel -->
                <div class="evidence-modal-remarks-pane" style="width: 320px; background-color: #1f2937; border-left: 1px solid #374151; display: flex; flex-direction: column; flex-shrink: 0;">
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

    @if($editingEvidenceId)
        <div wire:key="editor-wrapper-{{ $editingEvidenceId }}" @annotation-saved.window="$wire.closeEditor()">
            <livewire:operations.evidence-annotation-editor
                :evidence="\App\Domain\Audit\Models\AuditEvidence::find($editingEvidenceId)"
                :key="'editor-'.$editingEvidenceId"
            />
        </div>
    @endif

    <!-- Mobile Sticky Action / Progress Bar -->
    <div class="audit-mobile-sticky-bar">
        <div style="display: flex; flex-direction: column; gap: 0.125rem;">
            <div style="font-size: 0.75rem; font-weight: 700; color: #4b5563; text-transform: uppercase; letter-spacing: 0.05em;">
                {{ $completedRequirements }}/{{ $totalRequirements }} Tasks ({{ $progress }}%)
            </div>
            <div style="font-size: 0.8125rem; font-weight: 600; color: #111827;">
                @if($pendingItems === 0 && $hasVideo)
                    <span style="color: #16a34a;">Ready for approval</span>
                @else
                    <span>{{ $pendingItems }} item(s) pending</span>
                @endif
            </div>
        </div>

        <div>
            @if($audit->canSubmit() && $progress === 100)
                <button 
                    type="button" 
                    wire:click="mountAction('submitForReview')" 
                    style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.45rem 0.875rem; font-size: 0.8125rem; font-weight: 600; color: white; background-color: #16a34a; border-radius: 0.375rem; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1); cursor: pointer;"
                >
                    <x-filament::icon icon="heroicon-o-paper-airplane" style="width: 1rem; height: 1rem;" />
                    <span>Submit</span>
                </button>
            @elseif($audit->status === \App\Domain\Audit\Enums\AuditStatus::DRAFT && $audit->canStart())
                <button 
                    type="button" 
                    wire:click="mountAction('startAudit')" 
                    style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.45rem 0.875rem; font-size: 0.8125rem; font-weight: 600; color: white; background-color: #2563eb; border-radius: 0.375rem; border: none; box-shadow: 0 1px 2px rgba(0,0,0,0.1); cursor: pointer;"
                >
                    <x-filament::icon icon="heroicon-o-play" style="width: 1rem; height: 1rem;" />
                    <span>Start</span>
                </button>
            @endif
        </div>
    </div>
</div>

@script
<script>
    window.dictateRemarks = function(btn) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        if (!SpeechRecognition) {
            alert('Voice dictation is not supported by your current browser. Please use Chrome or Safari.');
            return;
        }

        const modalWindow = btn.closest('.fi-modal-window') || document;
        const textarea = modalWindow.querySelector('textarea[name*="remarks"], textarea');
        if (!textarea) return;

        if (window._activeRecognition) {
            try { window._activeRecognition.stop(); } catch(e) {}
            window._activeRecognition = null;
            btn.style.color = '';
            return;
        }

        const recognition = new SpeechRecognition();
        window._activeRecognition = recognition;
        recognition.continuous = false;
        recognition.interimResults = false;
        recognition.lang = 'en-US';

        btn.style.color = '#ef4444';

        recognition.onresult = function(event) {
            const text = event.results[0][0].transcript;
            if (text) {
                const current = textarea.value.trim();
                const updated = current ? (current + ' ' + text) : text;
                textarea.value = updated;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
                textarea.dispatchEvent(new Event('change', { bubbles: true }));
            }
        };

        recognition.onend = function() {
            btn.style.color = '';
            window._activeRecognition = null;
        };

        recognition.onerror = function() {
            btn.style.color = '';
            window._activeRecognition = null;
        };

        try {
            recognition.start();
        } catch(e) {
            btn.style.color = '';
            window._activeRecognition = null;
        }
    };

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


