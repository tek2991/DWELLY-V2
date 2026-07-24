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
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <span style="font-size: 1.125rem; font-weight: 600;">Audit Summary</span>
                <span style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); background: rgba(243, 244, 246, 1); padding: 0.25rem 0.75rem; border-radius: 9999px;">
                    Review Round: <strong>{{ $audit->review_round }}</strong>
                </span>
            </div>
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

    <!-- Shortcut Action Header -->
    @if($audit->canReview())
        <div style="display: flex; align-items: center; justify-content: space-between; background: rgba(249, 250, 251, 1); padding: 1rem 1.25rem; border-radius: 0.5rem; border: 1px solid rgba(229, 231, 235, 1);">
            <div>
                <strong style="font-size: 1rem; color: rgba(17, 24, 39, 1);">Review Controls</strong>
                <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1);">Accept items individually or use the shortcut below to accept all items at once.</div>
            </div>
            <div>
                {{ $this->acceptAllAction }}
            </div>
        </div>
    @endif

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
        <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 1rem;">
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
                                    </div>
                                    
                                    <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
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
                                        @elseif($item->status === \App\Domain\Audit\Enums\ItemStatus::INSPECTED)
                                            <span>&bull;</span>
                                            <x-filament::badge color="info" size="sm">
                                                Inspected (Awaiting Review)
                                            </x-filament::badge>
                                        @endif
                                    </div>

                                    @if($item->remarks)
                                        <div style="font-size: 0.875rem; color: rgba(75, 85, 99, 1); background: rgba(243, 244, 246, 1); padding: 0.5rem 0.75rem; border-radius: 0.25rem;">
                                            <strong>Inspector Remarks:</strong> {{ $item->remarks }}
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

                                <div style="display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;">
                                    @if($audit->canReview())
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
                                                $fullUrl = $media ? $media->getUrl() : null;
                                                $url = $fullUrl ? (parse_url($fullUrl, PHP_URL_PATH) ?: $fullUrl) : null;
                                                $hasAnnotations = !empty($ev->annotation_json['canvas']['objects']);
                                            @endphp
                                            @if($url)
                                                <div 
                                                    x-data
                                                    @click="$dispatch('open-evidence-modal', { imageUrl: '{{ $url }}', annotationJson: {{ json_encode($ev->annotation_json ?? null) }}, itemName: '{{ addslashes($item->name) }}' })"
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
        </div>
    @endif

    <!-- Evidence Modal with Fabric Annotation Viewer -->
    <div
        x-data="evidenceReviewModal"
        @open-evidence-modal.window="openModal($event.detail)"
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
