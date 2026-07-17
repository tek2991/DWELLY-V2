<div x-data="initAnnotorious(@js($imageUrl), @js($annotationData))" style="position: fixed; inset: 0; background: rgba(0, 0, 0, 0.75); display: flex; align-items: center; justify-content: center; z-index: 50; padding: 1rem;">
    <div style="background: white; border-radius: 0.75rem; width: 100%; max-width: 900px; max-height: 90vh; display: flex; flex-direction: column; overflow: hidden; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);">
        
        <div style="display: flex; align-items: center; justify-content: space-between; padding: 1rem; border-bottom: 1px solid rgba(229, 231, 235, 1);">
            <h3 style="font-size: 1.125rem; font-weight: 600; margin: 0; color: rgba(17, 24, 39, 1);">
                Annotorious Editor
            </h3>
            <div style="display: flex; gap: 0.5rem;">
                <button
                    type="button"
                    wire:click="closeEditor"
                    style="padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: rgba(55, 65, 81, 1); background-color: white; border: 1px solid rgba(209, 213, 219, 1); border-radius: 0.375rem; cursor: pointer;"
                >
                    Cancel
                </button>
            </div>
        </div>

        <div style="flex: 1; padding: 1rem; overflow-y: auto; display: flex; flex-direction: column; align-items: center; background: rgba(243, 244, 246, 1);">
            <div id="annotorious-container" style="width: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                <div style="text-align: center; color: rgba(107, 114, 128, 1); margin-top: 2rem;">Loading Annotorious...</div>
            </div>
        </div>

    </div>

    @script
    <script>
        Alpine.data('initAnnotorious', (imageUrl, initialData) => ({
            init() {
                const initEditor = () => {
                    const containerId = 'annotorious-container';

                    if (window.initAnnotoriousEditor) {
                        window.initAnnotoriousEditor(containerId, imageUrl, initialData, (annotations) => {
                            $wire.saveAnnotation(annotations);
                        });
                    } else {
                        // Try again in a moment if script hasn't loaded yet
                        setTimeout(initEditor, 100);
                    }
                };

                // Allow short delay for the DOM to render the container
                setTimeout(initEditor, 50);
            }
        }));
    </script>
    @endscript
</div>
