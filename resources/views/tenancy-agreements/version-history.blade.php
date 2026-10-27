@php
    $mediaItems = collect();
    
    if ($record) {
        $draftsPdf = $record->getMedia('draft_pdf')->sortByDesc('id');
        $draftsWord = $record->getMedia('draft_word')->sortByDesc('id');
        $signed = $record->getMedia('signed_agreement');
        
        $mediaItems = $signed->concat($draftsPdf)->concat($draftsWord)->sortByDesc('created_at');
    }

    $latestDraftPdf = $record?->getLatestDraftPdf();
    $latestDraftWord = $record?->getLatestDraftWord();
    $signedCopy = $record?->getSignedAgreement();

    $latestItems = collect([$signedCopy, $latestDraftPdf, $latestDraftWord])->filter();
    $latestIds = $latestItems->pluck('id')->all();
    $previousItems = $mediaItems->reject(fn ($item) => in_array($item->id, $latestIds));
@endphp

<div style="width: 100%;">
    @if ($mediaItems->isEmpty())
        <div style="display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-radius: 8px; border: 1px dashed rgba(128, 128, 128, 0.3); background-color: rgba(128, 128, 128, 0.03); color: #64748b;">
            <x-filament::icon icon="heroicon-o-document-text" style="width: 22px; height: 22px; color: #94a3b8; flex-shrink: 0;" />
            <div style="font-size: 13px;">
                <strong style="color: inherit;">No draft documents generated yet.</strong>
                <p style="margin: 2px 0 0 0; color: #94a3b8; font-size: 12px;">Click <strong>Generate Draft Documents</strong> above to compile the Leave & License Agreement with all Annexures.</p>
            </div>
        </div>
    @else
        <div style="display: flex; flex-direction: column; gap: 14px;">
            {{-- Latest Active Documents --}}
            <div>
                <h4 style="font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; margin: 0 0 8px 0; display: flex; align-items: center; gap: 6px;">
                    <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background-color: #10b981;"></span>
                    Latest Documents
                </h4>
                <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px;">
                    @foreach ($latestItems as $media)
                        @include('tenancy-agreements.partials.version-item', ['media' => $media, 'record' => $record])
                    @endforeach
                </ul>
            </div>

            {{-- Previous Versions (if any) --}}
            @if ($previousItems->isNotEmpty())
                <div x-data="{ expanded: false }" style="padding-top: 6px;">
                    <button type="button" 
                            @click="expanded = !expanded" 
                            style="display: flex; align-items: center; justify-content: space-between; width: 100%; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em; background: none; border: none; padding: 0; cursor: pointer; text-align: left;">
                        <span style="display: flex; align-items: center; gap: 6px;">
                            <span style="display: inline-block; width: 7px; height: 7px; border-radius: 50%; background-color: #94a3b8;"></span>
                            Previous Versions ({{ $previousItems->count() }})
                        </span>
                        <x-filament::icon icon="heroicon-m-chevron-down" style="width: 14px; height: 14px; transition: transform 0.2s;" ::style="expanded ? 'transform: rotate(180deg); width: 14px; height: 14px;' : 'width: 14px; height: 14px;'" />
                    </button>

                    <ul x-show="expanded" style="list-style: none; margin: 8px 0 0 0; padding: 0; display: flex; flex-direction: column; gap: 8px;">
                        @foreach ($previousItems as $media)
                            @include('tenancy-agreements.partials.version-item', ['media' => $media, 'record' => $record])
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
