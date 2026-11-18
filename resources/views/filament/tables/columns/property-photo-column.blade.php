@php
    $record = $getRecord();
    $url = $record?->url;
    $propertyId = $record?->property_id ?? 'gallery';
    $title = $record?->title ?: ($record?->room?->custom_name ?: ($record?->room?->roomDefinition?->name ?? 'Property Photo'));
    $roomName = $record?->room ? ($record->room->custom_name ?: ($record->room->roomDefinition?->name ?? 'Room')) : null;
@endphp

@if($url)
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
        class="py-1"
    >
        <a 
            data-fslightbox="property-photos-{{ $propertyId }}"
            data-type="image"
            href="{{ $url }}"
            title="Click to view full photo in gallery: {{ $title }}{{ $roomName ? ' (' . $roomName . ')' : '' }}"
            style="position: relative; display: block; width: 100px; height: 100px; border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(128, 128, 128, 0.2); background: rgba(128, 128, 128, 0.05); cursor: pointer; text-decoration: none;"
            class="group"
            x-on:click="if (typeof refreshFsLightbox === 'function') { refreshFsLightbox(); }"
        >
            <img 
                src="{{ $url }}" 
                alt="{{ $title }}"
                style="width: 100%; height: 100%; object-fit: cover; transition: transform 0.2s ease-in-out;"
                class="group-hover:scale-105"
                loading="lazy"
            />
            
            <!-- Hover Overlay with Magnifying Glass / Gallery Icon -->
            <div 
                style="position: absolute; inset: 0; background: rgba(0, 0, 0, 0.35); opacity: 0; transition: opacity 0.2s ease-in-out; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;"
                class="group-hover:!opacity-100"
            >
                <div style="background: rgba(0, 0, 0, 0.6); border-radius: 9999px; padding: 6px; color: #ffffff; display: flex; align-items: center; justify-content: center;">
                    <svg style="width: 16px; height: 16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0zM10 7v3m0 0v3m0-3h3m-3 0H7" />
                    </svg>
                </div>
                <span style="color: #ffffff; font-size: 10px; font-weight: 600; text-shadow: 0 1px 2px rgba(0,0,0,0.8);">Gallery</span>
            </div>

            @if($record?->is_featured)
                <span style="position: absolute; top: 4px; left: 4px; font-size: 9px; font-weight: 700; background: #f59e0b; color: #ffffff; padding: 1px 5px; border-radius: 4px; box-shadow: 0 1px 2px rgba(0,0,0,0.3);">
                    ★ Featured
                </span>
            @endif
        </a>
    </div>
@else
    <div style="width: 100px; height: 100px; border-radius: 0.5rem; border: 1px dashed rgba(128, 128, 128, 0.3); background: rgba(128, 128, 128, 0.05); display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 11px;">
        No Image
    </div>
@endif

