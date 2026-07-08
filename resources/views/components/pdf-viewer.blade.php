<div class="w-full rounded-lg overflow-hidden border border-gray-300 dark:border-gray-700">
    <iframe src="data:application/pdf;base64,{{ base64_encode(file_get_contents($record->getFirstMedia($mediaCollection)->getPath())) }}" class="w-full" style="min-height: 75vh;" frameborder="0"></iframe>
</div>
