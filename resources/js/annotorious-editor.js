import { createImageAnnotator } from '@annotorious/annotorious';
import '@annotorious/annotorious/annotorious.css';

window.initAnnotoriousEditor = function(containerId, imageUrl, initialData, onSave) {
    const container = document.getElementById(containerId);
    if (!container) return;

    // Create an image element
    const img = document.createElement('img');
    img.src = imageUrl;
    img.id = 'annotorious-image-' + Date.now();
    img.style.maxWidth = '100%';
    img.style.maxHeight = '70vh';
    img.style.display = 'block';
    img.style.margin = '0 auto';
    
    container.innerHTML = '';
    container.appendChild(img);

    // Initialize Annotorious after image is loaded to get correct dimensions
    img.onload = () => {
        const anno = createImageAnnotator(img.id);

        if (initialData && Array.isArray(initialData)) {
            anno.setAnnotations(initialData);
        }

        // Add a save button below
        const saveBtn = document.createElement('button');
        saveBtn.innerText = 'Save Annotorious Changes';
        saveBtn.style.marginTop = '1rem';
        saveBtn.style.padding = '0.5rem 1rem';
        saveBtn.style.backgroundColor = 'var(--primary-600, #6366f1)';
        saveBtn.style.color = 'white';
        saveBtn.style.border = 'none';
        saveBtn.style.borderRadius = '0.375rem';
        saveBtn.style.cursor = 'pointer';
        
        saveBtn.onclick = () => {
            const annotations = anno.getAnnotations();
            if (onSave) {
                onSave(annotations);
            }
        };

        container.appendChild(saveBtn);
    };
};
