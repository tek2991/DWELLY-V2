<div class="space-y-4">
    <div class="overflow-hidden bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 rounded-xl">
        <table class="w-full text-left text-sm text-gray-500 dark:text-gray-400">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr>
                    <th class="px-4 py-3 font-medium text-gray-950 dark:text-white">Version</th>
                    <th class="px-4 py-3 font-medium text-gray-950 dark:text-white">Generated At</th>
                    <th class="px-4 py-3 font-medium text-gray-950 dark:text-white">File Size</th>
                    <th class="px-4 py-3 font-medium text-gray-950 dark:text-white text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                @foreach($mou->getMedia('draft_pdf')->reverse() as $media)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-200">
                            Version {{ $media->getCustomProperty('version', '?') }}
                        </td>
                        <td class="px-4 py-3">
                            {{ $media->created_at->format('d M Y, h:i A') }}
                        </td>
                        <td class="px-4 py-3">
                            {{ $media->human_readable_size }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ $media->getUrl() }}" download="{{ $mou->number }}-draft-v{{ $media->getCustomProperty('version', '?') }}.pdf" class="text-primary-600 hover:text-primary-500 font-medium">
                                Download
                            </a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
