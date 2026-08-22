@props(['path', 'mimeType' => 'application/pdf'])

<div class="w-full rounded-lg overflow-hidden border border-gray-300 dark:border-gray-700 flex justify-center items-center bg-gray-50 dark:bg-gray-900" style="min-height: 75vh;">
    @if(file_exists($path))
        @if(str_starts_with($mimeType, 'image/'))
            <img src="data:{{ $mimeType }};base64,{{ base64_encode(file_get_contents($path)) }}" class="max-w-full max-h-[80vh] object-contain" alt="Document Preview">
        @else
            <iframe src="data:{{ $mimeType }};base64,{{ base64_encode(file_get_contents($path)) }}" class="w-full h-full" style="min-height: 75vh; width: 100%;" frameborder="0"></iframe>
        @endif
    @else
        <div class="p-8 text-center text-gray-500 dark:text-gray-400 flex flex-col items-center justify-center">
            <svg class="w-12 h-12 text-amber-500 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
            </svg>
            <p class="text-base font-semibold text-gray-800 dark:text-gray-200">Document file not found on disk</p>
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">The physical file is missing or not accessible at the configured path.</p>
        </div>
    @endif
</div>
