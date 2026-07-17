@php
    $item = $getRecord();
    $evidenceList = $item ? $item->evidence()->orderBy('display_order')->get() : collect();
@endphp

<div style="display: flex; flex-direction: column; gap: 1rem;">
    <div style="display: flex; align-items: center; justify-content: space-between;">
        <span style="font-size: 0.875rem; font-weight: 500;">Evidence Gallery</span>
        
        <label style="cursor: pointer; display: inline-flex; items-center; padding: 0.5rem 1rem; font-size: 0.875rem; font-weight: 500; color: white; background-color: var(--primary-600); border-radius: 0.5rem;">
            <x-filament::icon icon="heroicon-s-cloud-arrow-up" style="width: 1rem; height: 1rem; margin-right: 0.5rem;" />
            Upload Photos
            <input type="file" multiple wire:model.live="uploads" style="display: none;" accept="image/*">
        </label>
    </div>

    @if(count($uploads ?? []) > 0)
        <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); display: flex; align-items: center;">
            <x-filament::icon icon="heroicon-o-arrow-path" style="width: 1rem; height: 1rem; margin-right: 0.5rem;" class="animate-spin" /> Uploading...
        </div>
    @endif

    @if($evidenceList->isEmpty())
        <div style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); text-align: center; padding: 2rem 0; border: 2px dashed rgba(209, 213, 219, 1); border-radius: 0.5rem;">
            No evidence uploaded yet.
        </div>
    @else
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem;">
            @foreach($evidenceList as $evidence)
                @php
                    $media = $evidence->getFirstMedia('images');
                    $imageUrl = $media ? $media->getUrl() : '';
                    $annotationCount = is_array($evidence->annotation_json) && isset($evidence->annotation_json['canvas']['objects']) 
                        ? count($evidence->annotation_json['canvas']['objects']) 
                        : 0;
                    $isAnnotated = $evidence->status->value === 'annotated';
                @endphp
                <x-filament::section compact style="padding: 0; overflow: hidden; display: flex; flex-direction: column;">
                    <div style="aspect-ratio: 16/9; background-color: rgba(243, 244, 246, 1); position: relative; width: 100%;">
                        @if($imageUrl)
                            <img src="{{ $imageUrl }}" style="width: 100%; height: 100%; object-fit: cover;">
                        @else
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: rgba(156, 163, 175, 1);">
                                <x-filament::icon icon="heroicon-o-photo" style="width: 2rem; height: 2rem;" />
                            </div>
                        @endif
                        
                        @if($isAnnotated)
                            <div style="position: absolute; top: 0.5rem; right: 0.5rem;">
                                <x-filament::badge color="success" size="sm">ANNOTATED</x-filament::badge>
                            </div>
                        @endif
                    </div>
                    
                    <div style="padding: 0.75rem; display: flex; flex-direction: column; flex: 1;">
                        <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.875rem; color: rgba(75, 85, 99, 1); margin-bottom: 0.75rem;">
                            <span style="display: flex; align-items: center; gap: 0.25rem;" title="Annotations">
                                <x-filament::icon icon="heroicon-o-pencil" style="width: 1rem; height: 1rem;" />
                                {{ $annotationCount }}
                            </span>
                            @if($evidence->caption)
                                <span style="display: flex; align-items: center; gap: 0.25rem;" title="Caption">
                                    <x-filament::icon icon="heroicon-o-chat-bubble-bottom-center-text" style="width: 1rem; height: 1rem;" />
                                </span>
                            @endif
                        </div>
                        
                        <div style="margin-top: auto; display: flex; align-items: center; gap: 0.5rem;">
                            <x-filament::button type="button" size="sm" color="gray" style="flex: 1; justify-content: center;" x-on:click="Livewire.dispatch('open-evidence-editor', { evidenceId: '{{ $evidence->id }}' })">
                                Annotate
                            </x-filament::button>
                            <x-filament::button type="button" size="sm" color="danger" icon="heroicon-o-trash" icon-alias="evidence-gallery.delete" class="px-2" x-on:click="Livewire.dispatch('delete-evidence', { evidenceId: '{{ $evidence->id }}' })">
                            </x-filament::button>
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif
</div>
