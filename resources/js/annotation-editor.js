import * as fabric from 'fabric';

export class AnnotationEditor {
    constructor(canvasElement, imageUrl, initialJson = null, onUpdate = null) {
        this.canvas = new fabric.Canvas(canvasElement, {
            isDrawingMode: false,
            preserveObjectStacking: true,
        });
        
        this.imageUrl = imageUrl;
        this.onUpdate = onUpdate;
        
        this.history = [];
        this.historyIndex = -1;
        this.isHistoryAction = false;
        
        this.setupEvents();
        this.init(initialJson);
    }

    async init(initialJson) {
        // Load background image
        try {
            console.log('[AnnotationEditor] Loading image from:', this.imageUrl);
            const img = await fabric.Image.fromURL(this.imageUrl);
            console.log('[AnnotationEditor] Image loaded successfully', img.width, 'x', img.height);
            
            let maxWidth = this.canvas.getElement().parentNode.clientWidth;
            console.log('[AnnotationEditor] Parent clientWidth is:', maxWidth);
            
            if (maxWidth === 0) {
                maxWidth = window.innerWidth * 0.7; // Fallback
                console.log('[AnnotationEditor] Using fallback maxWidth:', maxWidth);
            }
            
            const scale = Math.min(1, maxWidth / img.width);
            console.log('[AnnotationEditor] Calculated scale:', scale);
            
            this.canvas.setDimensions({ width: img.width * scale, height: img.height * scale });
            console.log('[AnnotationEditor] Canvas dimensions set to:', this.canvas.width, 'x', this.canvas.height);
            
            img.set({
                originX: 'left',
                originY: 'top',
                scaleX: scale,
                scaleY: scale,
                selectable: false,
                evented: false,
            });
            
            this.canvas.backgroundImage = img;
            this.canvas.requestRenderAll();
            
            // Draw a quick red box to confirm canvas is working even if background image is broken
            const rect = new fabric.Rect({ left: 10, top: 10, width: 50, height: 50, fill: 'red' });
            this.canvas.add(rect);
            
            console.log('[AnnotationEditor] Background image and test rect added');
            
            if (initialJson && initialJson.canvas) {
                await new Promise(resolve => this.loadFromJson(initialJson.canvas, resolve));
            } else {
                this.saveHistory();
            }
        } catch (e) {
            console.error('[AnnotationEditor] Failed to load image:', e);
        }
    }

    setupEvents() {
        this.canvas.on('object:added', () => this.handleCanvasChange());
        this.canvas.on('object:modified', () => this.handleCanvasChange());
        this.canvas.on('object:removed', () => this.handleCanvasChange());
        
        // Handle selection for Alpine integration
        this.canvas.on('selection:created', (e) => this.notifySelection(e.selected[0]));
        this.canvas.on('selection:updated', (e) => this.notifySelection(e.selected[0]));
        this.canvas.on('selection:cleared', () => this.notifySelection(null));
    }

    handleCanvasChange() {
        if (!this.isHistoryAction) {
            this.saveHistory();
        }
        if (this.onUpdate) {
            this.onUpdate(this.getLayers());
        }
    }

    notifySelection(object) {
        if (this.onUpdate) {
            this.onUpdate(this.getLayers(), object ? object.id : null);
        }
    }

    saveHistory() {
        // Drop future history if we are in the middle of undo stack
        if (this.historyIndex < this.history.length - 1) {
            this.history = this.history.slice(0, this.historyIndex + 1);
        }
        
        this.history.push(JSON.stringify(this.canvas.toJSON(['id', 'remark', 'customType'])));
        this.historyIndex++;
    }

    undo() {
        if (this.historyIndex > 0) {
            this.isHistoryAction = true;
            this.historyIndex--;
            this.loadFromJson(JSON.parse(this.history[this.historyIndex]), () => {
                this.isHistoryAction = false;
                if (this.onUpdate) this.onUpdate(this.getLayers());
            });
        }
    }

    redo() {
        if (this.historyIndex < this.history.length - 1) {
            this.isHistoryAction = true;
            this.historyIndex++;
            this.loadFromJson(JSON.parse(this.history[this.historyIndex]), () => {
                this.isHistoryAction = false;
                if (this.onUpdate) this.onUpdate(this.getLayers());
            });
        }
    }

    generateId() {
        return Math.random().toString(36).substring(2, 9);
    }

    addShape(type) {
        this.canvas.isDrawingMode = false;
        let obj;
        const commonOpts = {
            id: this.generateId(),
            left: 100,
            top: 100,
            stroke: 'red',
            strokeWidth: 3,
            fill: 'transparent',
            remark: '',
            customType: type
        };

        if (type === 'rectangle') {
            obj = new fabric.Rect({ ...commonOpts, width: 100, height: 100 });
        } else if (type === 'circle') {
            obj = new fabric.Circle({ ...commonOpts, radius: 50 });
        } else if (type === 'arrow') {
            // Fabric doesn't have an arrow primitive, so we use a line/polygon or just line for simplicity here
            obj = new fabric.Line([100, 100, 200, 200], { ...commonOpts });
            // Advanced arrow requires grouped line and triangle
        } else if (type === 'line') {
            obj = new fabric.Line([100, 100, 200, 200], { ...commonOpts });
        } else if (type === 'text') {
            obj = new fabric.IText('Text', {
                id: this.generateId(),
                left: 100,
                top: 100,
                fill: 'red',
                fontSize: 24,
                remark: '',
                customType: 'text'
            });
        } else if (type === 'number') {
            const num = this.getLayers().filter(l => l.customType === 'number').length + 1;
            obj = new fabric.IText(`①`, {
                id: this.generateId(),
                left: 100,
                top: 100,
                fill: 'red',
                fontSize: 32,
                remark: '',
                customType: 'number'
            });
        }

        if (obj) {
            this.canvas.add(obj);
            this.canvas.setActiveObject(obj);
        }
    }

    enableDrawing(color = 'red', width = 3) {
        this.canvas.isDrawingMode = true;
        this.canvas.freeDrawingBrush.color = color;
        this.canvas.freeDrawingBrush.width = width;
        
        // When drawing ends, give the path an ID and customType
        this.canvas.on('path:created', (e) => {
            e.path.set({
                id: this.generateId(),
                remark: '',
                customType: 'freehand'
            });
            this.handleCanvasChange();
        });
    }

    disableDrawing() {
        this.canvas.isDrawingMode = false;
        this.canvas.off('path:created');
    }

    deleteSelected() {
        const active = this.canvas.getActiveObjects();
        if (active.length) {
            active.forEach(obj => this.canvas.remove(obj));
            this.canvas.discardActiveObject();
        }
    }

    updateRemark(id, remark) {
        const obj = this.canvas.getObjects().find(o => o.id === id);
        if (obj) {
            obj.set('remark', remark);
            this.saveHistory(); // save history explicitly since object:modified doesn't fire on custom prop change
            if (this.onUpdate) this.onUpdate(this.getLayers());
        }
    }

    selectObject(id) {
        const obj = this.canvas.getObjects().find(o => o.id === id);
        if (obj) {
            this.canvas.setActiveObject(obj);
            this.canvas.requestRenderAll();
        }
    }

    getLayers() {
        return this.canvas.getObjects().map(obj => ({
            id: obj.id,
            type: obj.customType || obj.type,
            remark: obj.remark || ''
        }));
    }

    serialize() {
        return {
            version: 1,
            canvas: this.canvas.toJSON(['id', 'remark', 'customType'])
        };
    }

    loadFromJson(json, callback) {
        this.canvas.loadFromJSON(json, () => {
            this.canvas.renderAll();
            if (callback) callback();
            if (this.onUpdate && !this.isHistoryAction) this.onUpdate(this.getLayers());
        });
    }
}

window.AnnotationEditor = AnnotationEditor;
