@php
    $record = $getRecord();
    $livewire = $getLivewire();

    // Determine Property model and focused photo ID
    $property = null;
    $selectedPhotoId = null;

    if ($record instanceof \App\Domain\Property\Models\Property) {
        $property = $record;
    } elseif ($record instanceof \App\Domain\Property\Models\PropertyPhoto) {
        $property = $record->property;
        $selectedPhotoId = $record->id;
    } elseif ($livewire && method_exists($livewire, 'getOwnerRecord')) {
        $owner = $livewire->getOwnerRecord();
        if ($owner instanceof \App\Domain\Property\Models\Property) {
            $property = $owner;
        }
    }

    $photos = $property 
        ? $property->photos()->with('room.roomDefinition')->orderBy('order_column')->get() 
        : collect();

    $galleryId = 'property-modal-gallery-' . ($property ? $property->id : 'default');
@endphp

<div 
    x-data="{
        init() {
            this.$nextTick(() => {
                if (typeof refreshFsLightbox === 'function') {
                    refreshFsLightbox();
                }
            });
        }
    }"
    style="width: 100%; display: flex; flex-direction: column; gap: 0.75rem;"
>
    @if($photos->isEmpty())
        <div style="padding: 1.5rem 1rem; border-radius: 0.5rem; border: 1px dashed rgba(128, 128, 128, 0.3); background: rgba(128, 128, 128, 0.03); font-size: 0.8125rem; color: #6b7280; text-align: center;">
            📷 No photos have been uploaded for this property yet.
        </div>
    @else
        <!-- Thumbnails Container -->
        <div style="background: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.15); border-radius: 0.75rem; padding: 0.875rem;">
            <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 0.75rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                <span style="display: flex; align-items: center; gap: 0.375rem;">
                    <span>📸 Property Photos ({{ $photos->count() }})</span>
                </span>
                <span style="font-weight: 400; text-transform: none; color: #2563eb; font-size: 0.75rem;">
                    Click thumbnail to open full gallery & swipe
                </span>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 0.75rem;">
                @foreach($photos as $index => $photo)
                    @php
                        $photoUrl = $photo->url;
                        $roomName = $photo->room ? ($photo->room->custom_name ?: ($photo->room->roomDefinition?->name ?? 'Room')) : 'General';
                        $photoTitle = $photo->title ?: $roomName;
                        $isSelected = ($selectedPhotoId && $photo->id === $selectedPhotoId);
                    @endphp
                    @if($photoUrl)
                        <div style="display: flex; flex-direction: column; gap: 0.375rem;">
                            <a 
                                data-fslightbox="{{ $galleryId }}"
                                data-type="image"
                                href="{{ $photoUrl }}"
                                title="Click to view full photo in gallery: {{ $photoTitle }}" 
                                style="position: relative; display: block; width: 100%; aspect-ratio: 1 / 1; border-radius: 0.5rem; overflow: hidden; border: {{ $isSelected ? '2px solid #2563eb' : '1px solid rgba(128, 128, 128, 0.2)' }}; background: rgba(128, 128, 128, 0.05); cursor: pointer; text-decoration: none;"
                                class="group"
                                x-on:click="if (typeof refreshFsLightbox === 'function') { refreshFsLightbox(); }"
                            >
                                <img 
                                    src="{{ $photoUrl }}" 
                                    style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.2s ease-in-out;" 
                                    class="group-hover:scale-105"
                                    alt="{{ $photoTitle }}" 
                                />
                                
                                <!-- Index Badge -->
                                <span style="position: absolute; bottom: 4px; right: 4px; font-size: 10px; font-weight: 700; background: rgba(0, 0, 0, 0.75); color: #ffffff; padding: 2px 5px; border-radius: 3px;">
                                    #{{ $index + 1 }}
                                </span>

                                @if($photo->is_featured)
                                    <span style="position: absolute; top: 4px; left: 4px; font-size: 9px; font-weight: 700; background: #f59e0b; color: #ffffff; padding: 1px 4px; border-radius: 3px;">
                                        ★ Featured
                                    </span>
                                @endif

                                @if($isSelected)
                                    <span style="position: absolute; top: 4px; right: 4px; font-size: 9px; font-weight: 700; background: #2563eb; color: #ffffff; padding: 1px 4px; border-radius: 3px;">
                                        Selected
                                    </span>
                                @endif

                                <!-- Hover Overlay with expand icon -->
                                <div 
                                    style="position: absolute; inset: 0; background: rgba(0, 0, 0, 0.3); opacity: 0; transition: opacity 0.2s ease-in-out; display: flex; align-items: center; justify-content: center;"
                                    class="group-hover:!opacity-100"
                                >
                                    <svg style="width: 20px; height: 20px; color: #ffffff;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                                    </svg>
                                </div>
                            </a>
                            
                            <!-- Label under thumbnail -->
                            <div style="font-size: 11px; line-height: 1.25; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $photoTitle }}">
                                <span style="font-weight: 600; color: inherit;">{{ $photoTitle }}</span>
                                @if($roomName && $roomName !== $photoTitle)
                                    <span style="color: #6b7280; font-size: 10px; display: block;">{{ $roomName }}</span>
                                @endif
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @endif
</div>

