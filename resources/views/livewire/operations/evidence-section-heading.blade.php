<div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
    <div style="display: flex; align-items: center; gap: 0.75rem;">
        <span style="font-size: 1rem; font-weight: 600; color: rgba(17, 24, 39, 1);">Evidence</span>
        <span style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); background: rgba(243, 244, 246, 1); padding: 0.125rem 0.5rem; border-radius: 9999px;">
            {{ $count }} photo(s)
        </span>
    </div>

    <label style="cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.375rem 0.75rem; font-size: 0.875rem; font-weight: 500; color: rgba(79, 70, 229, 1); background-color: rgba(238, 242, 255, 1); border-radius: 0.375rem; border: 1px solid rgba(199, 210, 254, 1); margin: -0.25rem 0;">
        <svg xmlns="http://www.w3.org/2000/svg" style="width: 1rem; height: 1rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
        </svg>
        Upload Photos
        <input type="file" multiple wire:model.live="uploads" style="display: none;" accept="image/*">
    </label>
</div>
