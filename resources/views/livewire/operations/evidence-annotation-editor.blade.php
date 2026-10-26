<div
    x-data="initAnnotationEditor(@js($imageUrl), @js($annotationJson))"
    style="position: fixed; inset: 0; z-index: 99999; display: flex; flex-direction: column; background-color: #111827; color: white; font-family: ui-sans-serif, system-ui, sans-serif; height: 100dvh; max-height: 100dvh; overflow: hidden;"
>
    <style>
        .annotation-tool-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 2.5rem;
            height: 2.5rem;
            padding: 0 0.5rem;
            border-radius: 0.375rem;
            background: transparent;
            border: 1px solid transparent;
            color: #d1d5db;
            cursor: pointer;
            font-size: 1.15rem;
            flex-shrink: 0;
            transition: all 0.15s ease;
        }
        .annotation-tool-btn:hover, .annotation-tool-btn:focus {
            background: #374151;
            color: #ffffff;
        }
        .annotation-tool-btn.active {
            background: #4f46e5;
            color: #ffffff;
            border-color: #4f46e5;
        }
        .annotation-tools-scroll {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            padding: 0.25rem 0;
        }
        .annotation-tools-scroll::-webkit-scrollbar {
            display: none;
        }
        @media (max-width: 767px) {
            .desktop-only-divider {
                display: none !important;
            }
            .annotation-main-workspace {
                flex-direction: column !important;
            }
            .annotation-canvas-pane {
                padding: 0.5rem !important;
            }
            .annotation-layers-pane {
                width: 100% !important;
                border-left: none !important;
                border-top: 1px solid #374151 !important;
            }
        }
        @media (min-width: 768px) {
            .mobile-only-tab-bar {
                display: none !important;
            }
        }
    </style>

    <!-- Header & Action Bar -->
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.625rem 1rem; background-color: #111827; border-bottom: 1px solid #374151; flex-shrink: 0; gap: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.75rem; min-width: 0;">
            <div style="font-weight: 600; font-size: 0.9375rem; color: #f9fafb; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                Photo Annotator
            </div>

            <!-- Mobile View Switcher Tab (Canvas vs Remarks) -->
            <div class="mobile-only-tab-bar" style="display: inline-flex; align-items: center; background: #1f2937; border-radius: 0.375rem; padding: 0.125rem; border: 1px solid #374151;">
                <button 
                    type="button" 
                    @click="activeMobileTab = 'canvas'" 
                    :style="activeMobileTab === 'canvas' ? 'background: #4f46e5; color: white;' : 'background: transparent; color: #9ca3af;'"
                    style="padding: 0.25rem 0.625rem; font-size: 0.75rem; font-weight: 600; border: none; border-radius: 0.25rem; cursor: pointer;"
                >
                    🎨 Canvas
                </button>
                <button 
                    type="button" 
                    @click="activeMobileTab = 'remarks'" 
                    :style="activeMobileTab === 'remarks' ? 'background: #4f46e5; color: white;' : 'background: transparent; color: #9ca3af;'"
                    style="padding: 0.25rem 0.625rem; font-size: 0.75rem; font-weight: 600; border: none; border-radius: 0.25rem; cursor: pointer; display: flex; align-items: center; gap: 0.25rem;"
                >
                    <span>📝 Notes</span>
                    <span x-text="layers.length" style="background: rgba(0,0,0,0.3); padding: 0 0.35rem; border-radius: 9999px; font-size: 0.6875rem;"></span>
                </button>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem; flex-shrink: 0;">
            <button 
                type="button" 
                @click="$dispatch('annotation-saved')" 
                style="padding: 0.4375rem 0.875rem; font-size: 0.8125rem; color: #d1d5db; background: transparent; border: 1px solid #4b5563; border-radius: 0.375rem; cursor: pointer;"
            >
                Cancel
            </button>
            <button 
                type="button"
                @click="saveAnnotationData()" 
                style="padding: 0.4375rem 1rem; font-size: 0.8125rem; font-weight: 600; color: white; background: #4f46e5; border: none; border-radius: 0.375rem; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.2);"
            >
                Save
            </button>
        </div>
    </div>

    <!-- Scrollable Touch Toolbar -->
    <div style="background-color: #1f2937; border-bottom: 1px solid #374151; padding: 0.375rem 0.75rem; flex-shrink: 0;">
        <div class="annotation-tools-scroll">
            <button @click="undo()" title="Undo" class="annotation-tool-btn">↩</button>
            <button @click="redo()" title="Redo" class="annotation-tool-btn">↪</button>
            
            <span style="width: 1px; height: 1.5rem; background: #4b5563; margin: 0 0.25rem; flex-shrink: 0;"></span>
            
            <button @click="addShape('rectangle')" title="Rectangle" class="annotation-tool-btn">▭</button>
            <button @click="addShape('circle')" title="Circle" class="annotation-tool-btn">○</button>
            <button @click="addShape('arrow')" title="Arrow" class="annotation-tool-btn">↗</button>
            <button @click="addShape('line')" title="Line" class="annotation-tool-btn">╱</button>
            <button @click="toggleDrawing()" :class="isDrawing ? 'active' : ''" title="Freehand Pen" class="annotation-tool-btn">✏️</button>
            <button @click="addShape('number')" title="Numbered Pin" class="annotation-tool-btn" style="font-size: 0.95rem; font-weight: 700;">①</button>
            <button @click="addShape('text')" title="Text" class="annotation-tool-btn" style="font-weight: 700; font-family: serif;">T</button>
            
            <span style="width: 1px; height: 1.5rem; background: #4b5563; margin: 0 0.25rem; flex-shrink: 0;"></span>
            
            <!-- Color Selector with Quick Presets for Touch -->
            <div style="display: flex; align-items: center; gap: 0.25rem; flex-shrink: 0; background: rgba(0,0,0,0.25); padding: 0.2rem 0.4rem; border-radius: 0.375rem;">
                <button type="button" @click="activeColor='#ef4444'; updateStyle();" style="width: 1.35rem; height: 1.35rem; border-radius: 0.25rem; background: #ef4444; border: 1px solid white; cursor: pointer; flex-shrink: 0;" title="Red"></button>
                <button type="button" @click="activeColor='#eab308'; updateStyle();" style="width: 1.35rem; height: 1.35rem; border-radius: 0.25rem; background: #eab308; border: 1px solid white; cursor: pointer; flex-shrink: 0;" title="Yellow"></button>
                <button type="button" @click="activeColor='#22c55e'; updateStyle();" style="width: 1.35rem; height: 1.35rem; border-radius: 0.25rem; background: #22c55e; border: 1px solid white; cursor: pointer; flex-shrink: 0;" title="Green"></button>
                <button type="button" @click="activeColor='#3b82f6'; updateStyle();" style="width: 1.35rem; height: 1.35rem; border-radius: 0.25rem; background: #3b82f6; border: 1px solid white; cursor: pointer; flex-shrink: 0;" title="Blue"></button>
                <input type="color" x-model="activeColor" @input="updateStyle()" title="Custom Color" style="width: 1.5rem; height: 1.5rem; padding: 0; border: none; border-radius: 0.25rem; background: transparent; cursor: pointer; flex-shrink: 0;" />
            </div>

            <!-- Stroke Width Slider -->
            <div style="display: flex; align-items: center; gap: 0.35rem; flex-shrink: 0; background: rgba(0,0,0,0.25); padding: 0.25rem 0.5rem; border-radius: 0.375rem;" title="Stroke Thickness">
                <span style="color: #9ca3af; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase;">Line</span>
                <input type="range" x-model="activeWidth" @input="updateStyle()" min="1" max="15" style="width: 4.5rem; cursor: pointer;" />
            </div>

            <!-- Background Fill Toggle -->
            <div style="display: flex; align-items: center; gap: 0.35rem; background: rgba(0,0,0,0.25); padding: 0.25rem 0.5rem; border-radius: 0.375rem; flex-shrink: 0;" title="Background Fill">
                <label style="display: flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; color: #9ca3af; cursor: pointer;">
                    <input type="checkbox" x-model="hasBgColor" @change="updateStyle()" style="cursor: pointer;" />
                    Fill
                </label>
                <input type="color" x-show="hasBgColor" x-model="activeBgColor" @input="updateStyle()" style="width: 1.35rem; height: 1.35rem; padding: 0; border: none; border-radius: 0.25rem; background: transparent; cursor: pointer;" />
            </div>

            <span style="width: 1px; height: 1.5rem; background: #4b5563; margin: 0 0.25rem; flex-shrink: 0;"></span>
            
            <button @click="deleteSelected()" title="Delete Selected" class="annotation-tool-btn" style="color: #f87171;">🗑</button>
        </div>
    </div>

    <!-- Main Workspace -->
    <div class="annotation-main-workspace" style="display: flex; flex: 1; overflow: hidden; position: relative;">
        <!-- Canvas Area -->
        <div 
            class="annotation-canvas-pane" 
            x-show="!isMobile || activeMobileTab === 'canvas'"
            style="flex: 1; background-color: #111827; overflow: auto; padding: 1.5rem; display: flex; align-items: center; justify-content: center; touch-action: none;"
        >
            <div wire:ignore style="box-shadow: 0 20px 30px rgba(0,0,0,0.5); outline: 1px solid rgba(255,255,255,0.1); border-radius: 0.375rem; overflow: hidden; display: flex; align-items: center; justify-content: center; margin: auto;">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>

        <!-- Layers Panel -->
        <div 
            class="annotation-layers-pane" 
            x-show="!isMobile || activeMobileTab === 'remarks'"
            style="width: 20rem; background-color: #1f2937; border-left: 1px solid #374151; display: flex; flex-direction: column; flex-shrink: 0; height: 100%;"
        >
            <div style="padding: 0.75rem 1rem; border-bottom: 1px solid #374151; font-size: 0.75rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; display: flex; align-items: center; justify-content: space-between;">
                <span>Annotations &amp; Remarks</span>
                <span x-text="layers.length" style="background: #374151; color: white; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem;"></span>
            </div>
            <div style="flex: 1; overflow-y: auto; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.625rem;">
                <template x-if="layers.length === 0">
                    <div style="color: #6b7280; font-size: 0.875rem; text-align: center; padding: 2.5rem 1rem;">
                        No annotations yet.<br>Use the toolbar above to mark areas on the photo.
                    </div>
                </template>
                
                <template x-for="(layer, index) in layers" :key="layer.id">
                    <div 
                        @click="selectObject(layer.id)"
                        style="padding: 0.625rem; border-radius: 0.375rem; cursor: pointer; border: 1px solid #374151; background: #111827;"
                    >
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.375rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8125rem; font-weight: 500; color: #d1d5db;">
                                <span x-text="index + 1" style="width: 1.25rem; height: 1.25rem; display: flex; align-items: center; justify-content: center; background: #374151; border-radius: 0.25rem; font-size: 0.75rem; flex-shrink: 0;"></span>
                                <span x-text="layer.type" style="text-transform: capitalize; color: #818cf8; font-weight: 600;"></span>
                            </div>
                            <button 
                                type="button" 
                                @click.stop="selectObject(layer.id); deleteSelected();" 
                                style="background: transparent; border: none; color: #ef4444; cursor: pointer; font-size: 0.875rem; padding: 0.125rem 0.375rem;" 
                                title="Delete"
                            >
                                ✕
                            </button>
                        </div>
                        <textarea 
                            x-model="layer.remark"
                            @input="updateRemark(layer.id, $event.target.value)"
                            @click.stop
                            style="width: 100%; background: #1f2937; border: 1px solid #374151; border-radius: 0.25rem; font-size: 0.8125rem; color: white; padding: 0.4rem; resize: none; font-family: inherit; box-sizing: border-box;" 
                            rows="2" 
                            placeholder="Add finding/defect remark..."
                        ></textarea>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>

@script
<script>
    Alpine.data('initAnnotationEditor', (imageUrl, initialJson) => {
        // Store editor OUTSIDE Alpine's reactive proxy to prevent Proxy wrapping
        // of Fabric.js internals. Alpine proxying the Fabric canvas breaks control
        // hit detection (obj.canvas becomes a Proxy, invalidating internal checks).
        let _editor = null;

        return {
            isDrawing: false,
            layers: [],
            activeColor: '#ef4444',
            activeWidth: 3,
            hasBgColor: false,
            activeBgColor: '#ffffff',
            isMobile: window.innerWidth < 768,
            activeMobileTab: 'canvas',

            init() {
                window.addEventListener('resize', () => {
                    this.isMobile = window.innerWidth < 768;
                });

                // Give the browser a tiny tick to paint the DOM, then init Fabric
                setTimeout(() => {
                    if (window.AnnotationEditor) {
                        _editor = new window.AnnotationEditor(
                            this.$refs.canvas,
                            imageUrl,
                            initialJson,
                            (updatedLayers) => {
                                this.layers = [...updatedLayers];
                            },
                            (id, color, width, hasBg, bgColor) => {
                                if (id) {
                                    this.activeColor = color;
                                    this.activeWidth = width;
                                    this.hasBgColor = hasBg;
                                    this.activeBgColor = bgColor;
                                }
                            }
                        );
                    } else {
                        console.error('AnnotationEditor class not found in window');
                    }
                }, 100);
            },

            addShape(type) { 
                _editor?.addShape(type, this.activeColor, this.activeWidth, this.hasBgColor ? this.activeBgColor : null); 
            },
            undo() { _editor?.undo(); },
            redo() { _editor?.redo(); },
            deleteSelected() { _editor?.deleteSelected(); },
            selectObject(id) { _editor?.selectObject(id); },
            updateRemark(id, val) { _editor?.updateRemark(id, val); },
            updateStyle() {
                _editor?.updateStyle(this.activeColor, this.activeWidth, this.hasBgColor ? this.activeBgColor : null);
                if (this.isDrawing) {
                    _editor?.enableDrawing(this.activeColor, this.activeWidth);
                }
            },

            toggleDrawing() {
                this.isDrawing = !this.isDrawing;
                if (this.isDrawing) {
                    _editor?.enableDrawing(this.activeColor, this.activeWidth);
                } else {
                    _editor?.disableDrawing();
                }
            },
            
            saveAnnotationData() {
                if (_editor) {
                    this.$wire.saveAnnotation(_editor.serialize());
                } else {
                    this.$dispatch('annotation-saved');
                }
            }
        };
    });
</script>
@endscript
