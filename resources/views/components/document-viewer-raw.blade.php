@props(['path', 'mimeType' => 'application/pdf'])

<div class="w-full rounded-lg overflow-hidden border border-gray-300 dark:border-gray-700 flex justify-center items-center bg-gray-50 dark:bg-gray-900" style="min-height: 75vh;">
    @if(str_starts_with($mimeType, 'image/'))
        <img src="data:{{ $mimeType }};base64,{{ base64_encode(file_get_contents($path)) }}" class="max-w-full max-h-[80vh] object-contain" alt="Document Preview">
    @else
        <iframe src="data:{{ $mimeType }};base64,{{ base64_encode(file_get_contents($path)) }}" class="w-full h-full" style="min-height: 75vh; width: 100%;" frameborder="0"></iframe>
    @endif
</div>
