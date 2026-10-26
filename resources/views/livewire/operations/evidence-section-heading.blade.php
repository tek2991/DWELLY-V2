<div x-data="{ isUploading: false, uploadProgress: 0 }"
     x-on:livewire-upload-start="isUploading = true; uploadProgress = 0"
     x-on:livewire-upload-finish="isUploading = false; uploadProgress = 0"
     x-on:livewire-upload-error="isUploading = false; uploadProgress = 0"
     x-on:livewire-upload-progress="uploadProgress = $event.detail.progress"
     style="display: flex; flex-direction: column; gap: 0.5rem; width: 100%;">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem; width: 100%;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 1rem; font-weight: 600; color: rgba(17, 24, 39, 1);">Evidence Photos</span>
            <span style="font-size: 0.8125rem; font-weight: 600; color: rgba(107, 114, 128, 1); background: rgba(243, 244, 246, 1); padding: 0.125rem 0.5rem; border-radius: 9999px;">
                {{ $count }} photo(s)
            </span>
        </div>

        @if($isEditable)
            <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                <!-- Direct Camera Button (Opens Camera directly on mobile) -->
                <label style="cursor: pointer; display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.4rem 0.75rem; font-size: 0.8125rem; font-weight: 600; color: white; background-color: #4f46e5; border-radius: 0.375rem; border: 1px solid #4338ca; box-shadow: 0 1px 2px rgba(0,0,0,0.05); user-select: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width: 1rem; height: 1rem;" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                    </svg>
                    <span>Snap Photo</span>
                    <input type="file" wire:model.live="uploads" style="display: none;" accept="image/*" capture="environment">
                </label>

                <!-- Gallery Upload Button -->
                <label style="cursor: pointer; display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.4rem 0.65rem; font-size: 0.8125rem; font-weight: 500; color: #374151; background-color: #f3f4f6; border-radius: 0.375rem; border: 1px solid #d1d5db; user-select: none;">
                    <svg xmlns="http://www.w3.org/2000/svg" style="width: 1rem; height: 1rem; color: #6b7280;" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                    </svg>
                    <span>Gallery</span>
                    <input type="file" multiple wire:model.live="uploads" style="display: none;" accept="image/*">
                </label>
            </div>
        @endif
    </div>

    <!-- Uploading Progress Indicator (strictly hidden until client-side upload begins) -->
    <div x-show="isUploading" x-cloak style="display: none;">
        <div style="font-size: 0.8125rem; font-weight: 500; color: #4f46e5; display: flex; align-items: center; justify-content: space-between; background: #eef2ff; padding: 0.375rem 0.625rem; border-radius: 0.375rem; border: 1px solid #c7d2fe;">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <svg class="animate-spin" style="width: 1rem; height: 1rem; animation: spin 1s linear infinite;" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle style="opacity: 0.25;" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path style="opacity: 0.75;" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Uploading photo... <span x-text="uploadProgress + '%'"></span></span>
            </div>
        </div>
        <div style="width: 100%; background: #e0e7ff; height: 3px; border-radius: 9999px; overflow: hidden; margin-top: 2px;">
            <div style="background: #4f46e5; height: 100%; transition: width 0.15s ease;" :style="'width: ' + uploadProgress + '%'"></div>
        </div>
    </div>
</div>
