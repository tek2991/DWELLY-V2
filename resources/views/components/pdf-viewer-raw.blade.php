<div class="w-full rounded-lg overflow-hidden border border-gray-300 dark:border-gray-700">
    @if(file_exists($path))
        <iframe src="data:application/pdf;base64,{{ base64_encode(file_get_contents($path)) }}" class="w-full" style="min-height: 75vh;" frameborder="0"></iframe>
    @else
        <div class="p-8 text-center bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400" style="min-height: 40vh; display: flex; flex-direction: column; align-items: center; justify-content: center;">
            <svg class="w-12 h-12 text-amber-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <p class="text-base font-semibold text-gray-800 dark:text-gray-200">Document file not found on disk</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">The physical file is missing or not accessible at the configured path.</p>
        </div>
    @endif
</div>
