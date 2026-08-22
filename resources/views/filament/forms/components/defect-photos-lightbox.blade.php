@php
    $record = $getRecord();
    $mediaItems = $record ? $record->getMedia('issue_photos') : collect();
    $galleryId = 'defect-gallery-' . ($record ? $record->id : 'preview');
    $images = [];
    foreach ($mediaItems as $index => $media) {
        $path = $media->getPath();
        if (file_exists($path)) {
            $mime = $media->mime_type ?: 'image/jpeg';
            $base64 = base64_encode(file_get_contents($path));
            $src = "data:{$mime};base64,{$base64}";
        } else {
            $src = $media->getUrl();
        }
        $images[] = [
            'src' => $src,
            'name' => $media->file_name ?? "Photo " . ($index + 1),
            'index' => $index,
        ];
    }
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
    style="width: 100%;"
>
    @if(empty($images))
        <div style="padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px dashed rgba(128, 128, 128, 0.3); background: rgba(128, 128, 128, 0.03); font-size: 0.75rem; color: #6b7280; display: flex; align-items: center; gap: 0.5rem;">
            <span>📷 No before-repair photos were recorded for this defect item.</span>
        </div>
    @else
        <!-- Thumbnails Container -->
        <div style="background: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.15); border-radius: 0.75rem; padding: 0.875rem;">
            <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 0.625rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
                <span style="display: flex; align-items: center; gap: 0.375rem;">
                    <span>📸 Before-Repair Photos ({{ count($images) }})</span>
                </span>
                <span style="font-weight: 400; text-transform: none; color: #2563eb; font-size: 0.75rem;">
                    Click thumbnail to open full gallery & swipe
                </span>
            </div>
            
            <div style="display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap;">
                @foreach($images as $img)
                    <a 
                        data-fslightbox="{{ $galleryId }}"
                        data-type="image"
                        href="{{ $img['src'] }}"
                        title="Click to view full photo in gallery" 
                        style="position: relative; display: block; width: 84px; height: 84px; border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(128, 128, 128, 0.2); background: rgba(128, 128, 128, 0.05); cursor: pointer; text-decoration: none;"
                    >
                        <img src="{{ $img['src'] }}" style="width: 100%; height: 100%; object-fit: cover;" alt="Before Photo #{{ $img['index'] + 1 }}" />
                        <span style="position: absolute; bottom: 4px; right: 4px; font-size: 10px; font-weight: 700; background: rgba(0, 0, 0, 0.75); color: #ffffff; padding: 2px 5px; border-radius: 3px;">#{{ $img['index'] + 1 }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
