import * as fabric from 'fabric';

// In Fabric v7, custom properties must be registered here to be included in toObject/toJSON serialization.
// This is the ONLY correct way - passing propertiesToInclude to toJSON only affects canvas-level props.
fabric.FabricObject.customProperties = ['id', 'remark', 'customType'];

function getRelativeUrl(url) {
    if (!url) return '';
    if (url.startsWith('http://') || url.startsWith('https://')) {
        try {
            const parsed = new URL(url);
            if (parsed.hostname === window.location.hostname || 
                parsed.hostname === 'localhost' || 
                parsed.hostname === '127.0.0.1') {
                return parsed.pathname + parsed.search + parsed.hash;
            }
        } catch(e) {}
    }
    return url;
}

export class AnnotationEditor {
    constructor(canvasElement, imageUrl, initialJson = null, onUpdate = null, onSelection = null) {
        this.canvas = new fabric.Canvas(canvasElement, {
            isDrawingMode: false,
            preserveObjectStacking: true,
        });
        
        this.imageUrl = getRelativeUrl(imageUrl);
        this.onUpdate = onUpdate;
        this.onSelection = onSelection;
        this._backgroundImg = null; // cached FabricImage for background
        
        this.history = [];
        this.historyIndex = -1;
        this.isHistoryAction = false;
        this.numberCounter = 1;
        
        this.setupEvents();
        this.init(initialJson);
    }

    async init(initialJson) {
        try {
            console.log('[AnnotationEditor] Loading image from:', this.imageUrl);
            const img = await fabric.Image.fromURL(this.imageUrl, { crossOrigin: 'anonymous' });
            console.log('[AnnotationEditor] Image loaded successfully', img.width, 'x', img.height);
            
            // Determine available space for the canvas based on window dimensions
            let availableWidth = Math.min(window.innerWidth - 380, 1150);
            let availableHeight = Math.min(window.innerHeight - 140, 720);
            if (availableWidth <= 0) availableWidth = 900;
            if (availableHeight <= 0) availableHeight = 650;
            
            let scale = Math.min(availableWidth / img.width, availableHeight / img.height);
            console.log('[AnnotationEditor] Calculated scale:', scale);
            
            // Size the canvas to match the scaled image
            const canvasW = Math.round(img.width * scale);
            const canvasH = Math.round(img.height * scale);
            this._canvasW = canvasW;
            this._canvasH = canvasH;
            this.canvas.setDimensions({ width: canvasW, height: canvasH });
            
            // Configure image as background
            img.set({
                originX: 'left',
                originY: 'top',
                left: 0,
                top: 0,
                scaleX: scale,
                scaleY: scale,
            });
            this._backgroundImg = img;
            this.canvas.backgroundImage = img;
            
            // Load saved annotation objects (if any), stripping any old background data
            if (initialJson && initialJson.canvas) {
                const jsonData = { ...initialJson.canvas };
                delete jsonData.backgroundImage; // remove any stale background
                delete jsonData.backgroundVpt;
                if (jsonData.objects) {
                    jsonData.objects = jsonData.objects.filter(
                        o => o.id !== 'bg-image' && o.customType !== 'background'
                    );
                }
                // In Fabric v7, loadFromJSON returns a Promise.
                // The reviver (2nd param) is called per-object to restore custom properties.
                await this._loadJson(jsonData);
                // loadFromJSON may clear/change backgroundImage and dimensions, so restore them
                this.canvas.setDimensions({ width: canvasW, height: canvasH });
                this.canvas.backgroundImage = img;
            }
            
            this.canvas.renderAll();
            console.log('[AnnotationEditor] Canvas rendered. Dims:', canvasW, 'x', canvasH);
            
            this.saveHistory();
            // Notify Alpine of the initial layers (e.g. when re-opening with saved annotations)
            if (this.onUpdate) this.onUpdate(this.getLayers());
        } catch (e) {
            console.error('[AnnotationEditor] Failed to load image:', e);
        }
    }

    setupEvents() {
        this.canvas.on('object:added', () => this.handleCanvasChange());
        this.canvas.on('object:modified', () => this.handleCanvasChange());
        this.canvas.on('object:removed', () => this.handleCanvasChange());
        
        this.canvas.on('selection:created', (e) => this.notifySelection(e.selected[0]));
        this.canvas.on('selection:updated', (e) => this.notifySelection(e.selected[0]));
        this.canvas.on('selection:cleared', () => this.notifySelection(null));

        // Mouse wheel zooming
        this.canvas.on('mouse:wheel', (opt) => {
            const delta = opt.e.deltaY;
            let zoom = this.canvas.getZoom();
            zoom *= 0.999 ** delta;
            if (zoom > 10) zoom = 10;
            if (zoom < 0.5) zoom = 0.5;
            this.canvas.zoomToPoint({ x: opt.e.offsetX, y: opt.e.offsetY }, zoom);
            opt.e.preventDefault();
            opt.e.stopPropagation();
        });
        
        // Always keep canvas offset fresh so control hit-areas stay accurate
        // when the editor is inside a scrollable/flex container
        this.canvas.on('mouse:over', () => {
            this.canvas.calcOffset();
        });
        
        // Alt+drag panning
        this.canvas.on('mouse:down', (opt) => {
            const evt = opt.e;
            if (evt.altKey === true) {
                this.isDragging = true;
                this.canvas.selection = false;
                this.lastPosX = evt.clientX;
                this.lastPosY = evt.clientY;
            }
        });
        this.canvas.on('mouse:move', (opt) => {
            if (this.isDragging) {
                const e = opt.e;
                const vpt = this.canvas.viewportTransform;
                vpt[4] += e.clientX - this.lastPosX;
                vpt[5] += e.clientY - this.lastPosY;
                this.canvas.requestRenderAll();
                this.lastPosX = e.clientX;
                this.lastPosY = e.clientY;
            }
        });
        this.canvas.on('mouse:up', () => {
            this.canvas.setViewportTransform(this.canvas.viewportTransform);
            this.isDragging = false;
            this.canvas.selection = true;
        });
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
        if (this.onSelection) {
            let color = '#ff0000';
            let width = 3;
            let hasBg = false;
            let bgColor = '#ffffff';
            if (object) {
                // If it's a text object or freehand (which uses stroke), extract properly
                color = (object.fill && object.fill !== 'transparent') ? object.fill : (object.stroke || '#ff0000');
                width = object.strokeWidth || 3;
                if (object.backgroundColor) {
                    hasBg = true;
                    bgColor = object.backgroundColor;
                }
            }
            this.onSelection(object ? object.id : null, color, width, hasBg, bgColor);
        }
        if (this.onUpdate) {
            this.onUpdate(this.getLayers(), object ? object.id : null);
        }
    }
    
    updateStyle(color, width, bgColor) {
        const activeObj = this.canvas.getActiveObject();
        if (activeObj) {
            if (activeObj.type === 'IText') {
                activeObj.set({ fill: color, backgroundColor: bgColor || '' });
            } else {
                // For shapes like rect/circle, apply fill if bgColor exists, else transparent
                activeObj.set({ stroke: color, strokeWidth: parseInt(width), fill: bgColor || 'transparent' });
            }
            this.canvas.requestRenderAll();
            this.saveHistory();
        }
    }

    saveHistory() {
        if (this.historyIndex < this.history.length - 1) {
            this.history = this.history.slice(0, this.historyIndex + 1);
        }
        // Serialize annotation objects only (background is excluded since we never serialize it)
        const json = this.canvas.toJSON();
        delete json.backgroundImage;
        this.history.push(JSON.stringify(json));
        this.historyIndex++;
    }

    async undo() {
        if (this.historyIndex > 0) {
            this.isHistoryAction = true;
            this.historyIndex--;
            await this._restoreHistory(this.history[this.historyIndex]);
            this.isHistoryAction = false;
            if (this.onUpdate) this.onUpdate(this.getLayers());
        }
    }

    async redo() {
        if (this.historyIndex < this.history.length - 1) {
            this.isHistoryAction = true;
            this.historyIndex++;
            await this._restoreHistory(this.history[this.historyIndex]);
            this.isHistoryAction = false;
            if (this.onUpdate) this.onUpdate(this.getLayers());
        }
    }

    async _restoreHistory(jsonString) {
        const json = JSON.parse(jsonString);
        await this._loadJson(json);
        // Restore our background and dimensions
        if (this._backgroundImg) {
            this.canvas.backgroundImage = this._backgroundImg;
        }
        this.canvas.setDimensions({ width: this._canvasW, height: this._canvasH });
        this.canvas.renderAll();
    }

    // Wraps canvas.loadFromJSON with a reviver to restore custom properties (id, remark, customType)
    // that Fabric v7 does not restore automatically.
    async _loadJson(jsonData) {
        await this.canvas.loadFromJSON(jsonData, (jsonObj, instance, error) => {
            if (instance && !error) {
                if (jsonObj.id !== undefined) instance.id = jsonObj.id;
                if (jsonObj.remark !== undefined) instance.remark = jsonObj.remark;
                if (jsonObj.customType !== undefined) instance.customType = jsonObj.customType;
            }
        });
    }

    generateId() {
        return Math.random().toString(36).substring(2, 9);
    }

    async addShape(type, color = '#ff0000', width = 3, bgColor = null) {
        this.canvas.isDrawingMode = false;
        
        const commonOpts = {
            id: this.generateId(),
            left: 100,
            top: 100,
            stroke: color,
            strokeWidth: parseInt(width),
            fill: type === 'text' || type === 'number' || type === 'line' || type === 'arrow' ? '' : (bgColor || 'transparent'),
            remark: '',
            customType: type,
            strokeUniform: true,
            padding: 5
        };

        let jsonObj;
        if (type === 'rectangle') {
            jsonObj = { type: 'Rect', ...commonOpts, width: 100, height: 100 };
        } else if (type === 'circle') {
            jsonObj = { type: 'Circle', ...commonOpts, radius: 50 };
        } else if (type === 'line') {
            jsonObj = { type: 'Line', ...commonOpts, x1: 100, y1: 100, x2: 200, y2: 200 };
        } else if (type === 'arrow') {
            // Diagonal arrow pointing bottom-right
            jsonObj = { type: 'Path', ...commonOpts, path: 'M 0 0 L 100 100 L 80 100 M 100 100 L 100 80', fill: '' };
        } else if (type === 'text') {
            jsonObj = { type: 'IText', ...commonOpts, text: 'Text', fontSize: 24, fill: color, stroke: null, strokeWidth: 0, backgroundColor: bgColor || '' };
        } else if (type === 'number') {
            let numText = this.numberCounter <= 20 ? String.fromCharCode(9311 + this.numberCounter) : this.numberCounter.toString();
            jsonObj = { type: 'IText', ...commonOpts, text: numText, fontSize: 32, fill: color, stroke: null, strokeWidth: 0, backgroundColor: bgColor || '' };
            this.numberCounter++;
        }

        if (jsonObj) {
            try {
                const classObj = fabric[jsonObj.type];
                if (classObj) {
                    // In Fabric 7, fromObject might behave unpredictably with identity.
                    // Since these are new shapes, we can just instantiate them directly!
                    const objType = jsonObj.type;
                    delete jsonObj.type; // Avoid setter error

                    let obj;
                    if (objType === 'Line') {
                        // Line constructor: new fabric.Line([x1, y1, x2, y2], options)
                        obj = new classObj([jsonObj.x1, jsonObj.y1, jsonObj.x2, jsonObj.y2], jsonObj);
                    } else if (objType === 'Path') {
                        // Path constructor: new fabric.Path(pathString, options)
                        obj = new classObj(jsonObj.path, jsonObj);
                    } else if (objType === 'IText') {
                        // IText constructor: new fabric.IText(text, options)
                        obj = new classObj(jsonObj.text, jsonObj);
                    } else {
                        obj = new classObj(jsonObj);
                    }
                    
                    this.canvas.add(obj);
                    this.canvas.calcOffset();
                    this.canvas.setActiveObject(obj);
                    this.canvas.requestRenderAll();
                }
            } catch (e) {
                console.error("Error adding shape via JSON:", e);
            }
        }
    }

    enableDrawing(color = '#ff0000', width = 3) {
        this.canvas.isDrawingMode = true;
        if (!this.canvas.freeDrawingBrush) {
            this.canvas.freeDrawingBrush = new fabric.PencilBrush(this.canvas);
        }
        this.canvas.freeDrawingBrush.color = color;
        this.canvas.freeDrawingBrush.width = parseInt(width);
        
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
            this.saveHistory();
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
        const json = this.canvas.toJSON();
        delete json.backgroundImage;
        return {
            version: 1,
            canvas: json
        };
    }
}

export class AnnotationViewer {
    constructor(canvasElement, imageUrl, initialJson = null, onUpdate = null) {
        this.canvas = new fabric.Canvas(canvasElement, {
            isDrawingMode: false,
            selection: false,
            preserveObjectStacking: true,
        });
        
        this.imageUrl = getRelativeUrl(imageUrl);
        this.onUpdate = onUpdate;
        this.init(initialJson);
    }

    async init(initialJson) {
        try {
            console.log('[AnnotationViewer] Loading image from:', this.imageUrl);
            const img = await fabric.Image.fromURL(this.imageUrl, { crossOrigin: 'anonymous' });
            
            let availableWidth = Math.min(window.innerWidth - 460, 920);
            let availableHeight = Math.min(window.innerHeight - 220, 600);
            if (availableWidth <= 0) availableWidth = 750;
            if (availableHeight <= 0) availableHeight = 500;

            let scale = Math.min(availableWidth / img.width, availableHeight / img.height);

            const canvasW = Math.round(img.width * scale);
            const canvasH = Math.round(img.height * scale);
            this.canvas.setDimensions({ width: canvasW, height: canvasH });

            img.set({
                originX: 'left',
                originY: 'top',
                left: 0,
                top: 0,
                scaleX: scale,
                scaleY: scale,
            });
            this.canvas.backgroundImage = img;

            if (initialJson && initialJson.canvas) {
                const jsonData = { ...initialJson.canvas };
                delete jsonData.backgroundImage;
                delete jsonData.backgroundVpt;
                if (jsonData.objects) {
                    jsonData.objects = jsonData.objects.filter(
                        o => o.id !== 'bg-image' && o.customType !== 'background'
                    );
                }
                await this.canvas.loadFromJSON(jsonData, (jsonObj, instance, error) => {
                    if (instance && !error) {
                        if (jsonObj.id !== undefined) instance.id = jsonObj.id;
                        if (jsonObj.remark !== undefined) instance.remark = jsonObj.remark;
                        if (jsonObj.customType !== undefined) instance.customType = jsonObj.customType;
                        instance.selectable = false;
                        instance.evented = false;
                    }
                });
                this.canvas.setDimensions({ width: canvasW, height: canvasH });
                this.canvas.backgroundImage = img;
            }

            this.canvas.renderAll();
            if (this.onUpdate) {
                this.onUpdate(this.getLayers());
            }
        } catch (e) {
            console.error('[AnnotationViewer] Failed to load image:', e);
        }
    }

    getLayers() {
        return this.canvas.getObjects().map(obj => ({
            id: obj.id,
            type: obj.customType || obj.type,
            remark: obj.remark || (obj.type === 'IText' ? obj.text : '')
        }));
    }
}

window.AnnotationEditor = AnnotationEditor;
window.AnnotationViewer = AnnotationViewer;
