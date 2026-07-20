@php
    $mediaItems = collect();
    
    if ($record) {
        $drafts = $record->getMedia('draft_pdf');
        $signed = $record->getMedia('signed_pdf');
        $archived = $record->getMedia('archived_signed_pdf');
        $kyc = $record->getMedia('kyc_documents');
        
        $mediaItems = $drafts->concat($signed)->concat($archived)->concat($kyc)->sortByDesc('created_at');
    }
@endphp

<div class="w-full">
    @if($mediaItems->isEmpty())
        <span class="text-sm text-gray-500 dark:text-gray-400">No documents generated yet.</span>
    @else
        @if($mediaItems->count() > 1)
            <div class="mb-4">
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Latest Document</h4>
                <ul class="flex flex-col space-y-2">
                    @include('mou.partials.version-item', ['media' => $mediaItems->first()])
                </ul>
            </div>
            
            <div>
                <h4 class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Previous Versions</h4>
                <ul class="flex flex-col space-y-2">
                    @foreach($mediaItems->slice(1) as $media)
                        @include('mou.partials.version-item', ['media' => $media])
                    @endforeach
                </ul>
            </div>
        @else
            <ul class="flex flex-col space-y-2">
                @include('mou.partials.version-item', ['media' => $mediaItems->first()])
            </ul>
        @endif
    @endif
</div>
