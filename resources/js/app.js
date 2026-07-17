import './annotation-editor';

function registerEvidenceEditor() {
    Alpine.data('evidenceAnnotationEditor', (config) => ({
        editor: null,
        isDrawing: false,
        layers: [],
        selectedId: null,
        
        init() {
            this.$nextTick(() => {
                this.initEditor();
            });
        },
        
        initEditor() {
            if (window.AnnotationEditor) {
                this.editor = new window.AnnotationEditor(
                    this.$refs.canvas, 
                    config.imageUrl, 
                    config.initialJson,
                    (layers, selectedId = null) => {
                        this.layers = layers;
                        if (selectedId !== undefined) {
                            this.selectedId = selectedId;
                        }
                    }
                );
            } else {
                setTimeout(() => this.initEditor(), 100);
            }
        },
        
        toggleDrawing() {
            this.isDrawing = !this.isDrawing;
            if (this.isDrawing) {
                this.editor.enableDrawing();
            } else {
                this.editor.disableDrawing();
            }
        },
        
        save() {
            const json = this.editor.serialize();
            this.$wire.saveAnnotation(json.canvas);
        }
    }));
}

if (window.Alpine) {
    registerEvidenceEditor();
} else {
    document.addEventListener('alpine:init', registerEvidenceEditor);
}
