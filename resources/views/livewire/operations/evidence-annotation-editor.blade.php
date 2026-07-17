<div
    x-data="initAnnotationEditor(@js($imageUrl), @js($annotationJson))"
    style="position: fixed; inset: 0; z-index: 99999; display: flex; flex-direction: column; background-color: #111827; color: white; font-family: ui-sans-serif, system-ui, sans-serif;"
>
    <!-- Toolbar -->
    <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background-color: #1f2937; border-bottom: 1px solid #374151; flex-shrink: 0; gap: 1rem;">
        <div style="display: flex; align-items: center; gap: 0.25rem; flex-wrap: wrap;">
            <button @click="undo()" title="Undo" style="padding:0.5rem; border-radius:0.375rem; background:transparent; border:none; color:#d1d5db; cursor:pointer; font-size:1.25rem;">↩</button>
            <button @click="redo()" title="Redo" style="padding:0.5rem; border-radius:0.375rem; background:transparent; border:none; color:#d1d5db; cursor:pointer; font-size:1.25rem;">↪</button>
            <span style="width:1px; height:1.5rem; background:#4b5563; margin:0 0.4rem; flex-shrink:0;"></span>
            <button @click="addShape('rectangle')" title="Rectangle" style="padding:0.5rem; border-radius:0.375rem; background:transparent; border:none; color:#d1d5db; cursor:pointer; font-size:1.25rem;">▭</button>
            <button @click="addShape('circle')" title="Circle" style="padding:0.5rem; border-radius:0.375rem; background:transparent; border:none; color:#d1d5db; cursor:pointer; font-size:1.25rem;">○</button>
            <button @click="addShape('line')" title="Line" style="padding:0.5rem; border-radius:0.375rem; background:transparent; border:none; color:#d1d5db; cursor:pointer; font-size:1.25rem;">╱</button>
            <button @click="addShape('arrow')" title="Arrow" style="padding:0.5rem; border-radius:0.375rem; background:transparent; border:none; color:#d1d5db; cursor:pointer; font-size:1.25rem;">↗</button>
            <span style="width:1px; height:1.5rem; background:#4b5563; margin:0 0.4rem; flex-shrink:0;"></span>
            <button @click="toggleDrawing()" :style="isDrawing ? 'background:#4f46e5; color:white; border-color:#4f46e5;' : 'background:transparent; color:#d1d5db; border-color:transparent;'" title="Freehand" style="padding:0.5rem; border-radius:0.375rem; border:1px solid transparent; cursor:pointer; font-size:1.25rem;">✏️</button>
            <span style="width:1px; height:1.5rem; background:#4b5563; margin:0 0.4rem; flex-shrink:0;"></span>
            <button @click="addShape('text')" title="Text" style="padding:0.5rem; border-radius:0.375rem; background:transparent; border:none; color:#d1d5db; cursor:pointer; font-size:1.25rem; font-weight:bold; font-family:serif;">T</button>
            <button @click="addShape('number')" title="Number" style="padding:0.4rem 0.5rem; border-radius:0.375rem; background:transparent; border:1px solid #6b7280; color:#d1d5db; cursor:pointer; font-size:1rem; font-weight:bold;">①</button>
            <span style="width:1px; height:1.5rem; background:#4b5563; margin:0 0.4rem; flex-shrink:0;"></span>
            <input type="color" x-model="activeColor" @input="updateStyle()" title="Color" style="width:2rem; height:2rem; padding:0; border:none; border-radius:0.25rem; background:transparent; cursor:pointer;" />
            <div style="display:flex; align-items:center; gap:0.25rem;" title="Thickness">
                <span style="color:#9ca3af; font-size:0.75rem;">Width:</span>
                <input type="range" x-model="activeWidth" @input="updateStyle()" min="1" max="15" style="width:8rem; cursor:pointer;" />
            </div>
            <span style="width:1px; height:1.5rem; background:#4b5563; margin:0 0.4rem; flex-shrink:0;"></span>
            <div style="display:flex; align-items:center; gap:0.25rem; background:rgba(255,255,255,0.05); padding:0.25rem 0.5rem; border-radius:0.25rem;" title="Background Fill">
                <label style="display:flex; align-items:center; gap:0.25rem; font-size:0.75rem; color:#9ca3af; cursor:pointer;">
                    <input type="checkbox" x-model="hasBgColor" @change="updateStyle()" style="cursor:pointer;" />
                    Fill
                </label>
                <input type="color" x-show="hasBgColor" x-model="activeBgColor" @input="updateStyle()" style="width:1.5rem; height:1.5rem; padding:0; border:none; border-radius:0.25rem; background:transparent; cursor:pointer;" />
            </div>
            <span style="width:1px; height:1.5rem; background:#4b5563; margin:0 0.4rem; flex-shrink:0;"></span>
            <button @click="deleteSelected()" title="Delete Selected" style="padding:0.5rem; border-radius:0.375rem; background:transparent; border:none; color:#f87171; cursor:pointer; font-size:1.25rem;">🗑</button>
        </div>
        <div style="display:flex; align-items:center; gap:0.75rem; flex-shrink:0;">
            <button type="button" @click="$dispatch('annotation-saved')" style="padding:0.5rem 1rem; font-size:0.875rem; color:#d1d5db; background:transparent; border:1px solid #4b5563; border-radius:0.5rem; cursor:pointer;">Cancel</button>
            <button @click="saveAnnotationData()" style="padding:0.5rem 1rem; font-size:0.875rem; font-weight:600; color:white; background:#4f46e5; border:none; border-radius:0.5rem; cursor:pointer;">Save Annotation</button>
        </div>
    </div>

    <!-- Main Workspace -->
    <div style="display:flex; flex:1; overflow:hidden;">
        <!-- Canvas Area -->
        <div style="flex:1; background-color:#111827; overflow:auto; padding:2rem; display:flex; align-items:center; justify-content:center;">
            <div wire:ignore style="box-shadow:0 25px 50px rgba(0,0,0,0.5); outline:1px solid rgba(255,255,255,0.1);">
                <canvas x-ref="canvas"></canvas>
            </div>
        </div>

        <!-- Layers Panel -->
        <div style="width:20rem; background-color:#1f2937; border-left:1px solid #374151; display:flex; flex-direction:column; flex-shrink:0;">
            <div style="padding:1rem; border-bottom:1px solid #374151; font-size:0.75rem; font-weight:600; color:#9ca3af; text-transform:uppercase; letter-spacing:0.05em;">
                Annotations &amp; Remarks
            </div>
            <div style="flex:1; overflow-y:auto; padding:1rem; display:flex; flex-direction:column; gap:0.75rem;">
                <template x-if="layers.length === 0">
                    <div style="color:#6b7280; font-size:0.875rem; text-align:center; padding:2rem 0;">
                        No annotations yet.<br>Use the toolbar above to draw.
                    </div>
                </template>
                
                <template x-for="(layer, index) in layers" :key="layer.id">
                    <div 
                        @click="selectObject(layer.id)"
                        style="padding:0.75rem; border-radius:0.5rem; cursor:pointer; border:1px solid #374151; background:#1f2937;"
                    >
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-bottom:0.5rem; font-size:0.875rem; font-weight:500; color:#d1d5db;">
                            <span x-text="index + 1" style="width:1.25rem; height:1.25rem; display:flex; align-items:center; justify-content:center; background:#374151; border-radius:0.25rem; font-size:0.75rem; flex-shrink:0;"></span>
                            <span x-text="layer.type" style="text-transform:capitalize;"></span>
                        </div>
                        <textarea 
                            x-model="layer.remark"
                            @input="updateRemark(layer.id, $event.target.value)"
                            @click.stop
                            style="width:100%; background:#111827; border:1px solid #374151; border-radius:0.375rem; font-size:0.875rem; color:white; padding:0.5rem; resize:none; font-family:inherit; box-sizing:border-box;" 
                            rows="2" 
                            placeholder="Add remark..."
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
            activeColor: '#ff0000',
            activeWidth: 3,
            hasBgColor: false,
            activeBgColor: '#ffffff',

            init() {
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
                    this.$wire.saveAnnotation(_editor.serialize().canvas);
                }
            }
        };
    });
</script>
@endscript
