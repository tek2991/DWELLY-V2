<div style="display: flex; flex-direction: column; gap: 1rem; padding: 1rem 0;">

    {{-- Upload bar --}}
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <span style="font-size: 0.875rem; font-weight: 600; color: rgba(55, 65, 81, 1);">
            {{ $evidenceList->count() }} photo(s)
        </span>
        <label style="cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: white; background-color: var(--primary-600, #6366f1); border-radius: 0.5rem;">
            <svg xmlns="http://www.w3.org/2000/svg" style="width: 1rem; height: 1rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 16.5V9.75m0 0 3 3m-3-3-3 3M6.75 19.5a4.5 4.5 0 0 1-1.41-8.775 5.25 5.25 0 0 1 10.233-2.33 3 3 0 0 1 3.758 3.848A3.752 3.752 0 0 1 18 19.5H6.75Z" />
            </svg>
            Upload Photos
            <input type="file" multiple wire:model.live="uploads" style="display: none;" accept="image/*">
        </label>
    </div>



    @if($evidenceList->isEmpty())
        <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); text-align: center; padding: 3rem 0; border: 2px dashed rgba(209, 213, 219, 1); border-radius: 0.5rem;">
            No evidence uploaded yet. Upload your first photo above.
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 1rem;">
            @foreach($evidenceList as $evidence)
                @php
                    $media = $evidence->getFirstMedia('images');
                    $imageUrl = '';
                    if ($media) {
                        $url = $media->getUrl();
                        $appUrl = rtrim(config('app.url'), '/');
                        if (str_starts_with($url, $appUrl)) {
                            $url = substr($url, strlen($appUrl));
                            if (!str_starts_with($url, '/')) {
                                $url = '/' . $url;
                            }
                        }
                        $imageUrl = $url;
                    }
                    $annotationCount = is_array($evidence->annotation_json) && isset($evidence->annotation_json['canvas']['objects'])
                        ? count($evidence->annotation_json['canvas']['objects'])
                        : 0;
                    $isAnnotated = $evidence->status->value === 'annotated';
                @endphp
                <div style="background: white; border: 1px solid rgba(229, 231, 235, 1); border-radius: 0.75rem; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.07); display: flex; flex-direction: column;">
                    <div style="aspect-ratio: 16/9; background: rgba(243, 244, 246, 1); position: relative; width: 100%;">
                        @if($imageUrl)
                            <img src="{{ $imageUrl }}" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: rgba(156, 163, 175, 1);">
                                <svg xmlns="http://www.w3.org/2000/svg" style="width: 2rem; height: 2rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 15.75 5.159-5.159a2.25 2.25 0 0 1 3.182 0l5.159 5.159m-1.5-1.5 1.409-1.409a2.25 2.25 0 0 1 3.182 0l2.909 2.909m-18 3.75h16.5a1.5 1.5 0 0 0 1.5-1.5V6a1.5 1.5 0 0 0-1.5-1.5H3.75A1.5 1.5 0 0 0 2.25 6v12a1.5 1.5 0 0 0 1.5 1.5Zm10.5-11.25h.008v.008h-.008V8.25Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                </svg>
                            </div>
                        @endif

                        @if($isAnnotated)
                            <div style="position: absolute; top: 0.5rem; right: 0.5rem; background: rgba(34, 197, 94, 1); color: white; font-size: 0.65rem; font-weight: 700; padding: 0.2rem 0.5rem; border-radius: 9999px; letter-spacing: 0.05em;">
                                ANNOTATED
                            </div>
                        @endif
                    </div>

                    <div style="padding: 0.75rem; display: flex; flex-direction: column; flex: 1; gap: 0.75rem;">
                        <div style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.8rem; color: rgba(107, 114, 128, 1);">
                            <svg xmlns="http://www.w3.org/2000/svg" style="width: 0.875rem; height: 0.875rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            {{ $annotationCount }} annotation(s)
                        </div>

                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <button
                                type="button"
                                wire:click="openEditor('{{ $evidence->id }}')"
                                style="flex: 1; padding: 0.4rem 0.5rem; font-size: 0.75rem; font-weight: 600; color: white; background-color: rgba(17, 24, 39, 1); border: none; border-radius: 0.4rem; cursor: pointer; min-width: max-content;"
                            >
                                Annotate
                            </button>

                            <button
                                type="button"
                                wire:click="deleteEvidence('{{ $evidence->id }}')"
                                wire:confirm="Delete this photo permanently?"
                                style="padding: 0.4rem 0.6rem; font-size: 0.8rem; font-weight: 600; color: rgba(220, 38, 38, 1); background-color: rgba(254, 242, 242, 1); border: none; border-radius: 0.4rem; cursor: pointer;"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" style="width: 0.9rem; height: 0.9rem;" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
