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
    class="w-full"
>
    @if(empty($images))
        <div style="padding: 10px 14px; border-radius: 8px; border: 1px dashed rgba(128, 128, 128, 0.25); background: rgba(128, 128, 128, 0.02); font-size: 12px; color: #64748b; display: flex; align-items: center; gap: 8px;">
            <span>📷 No before-repair photos were recorded for this defect item.</span>
        </div>
    @else
        <!-- Thumbnails Container -->
        <div style="background: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.15); border-radius: 8px; padding: 12px 16px;">
            <div style="font-size: 11px; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; margin-bottom: 8px; display: flex; align-items: center; justify-content: space-between;">
                <span style="display: flex; align-items: center; gap: 6px;">
                    <span>📸 Before-Repair Photos ({{ count($images) }})</span>
                </span>
                <span style="font-weight: 500; text-transform: none; color: #2563eb; font-size: 11px;">
                    Click thumbnail to open full gallery & swipe
                </span>
            </div>
            
            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                @foreach($images as $img)
                    <a 
                        data-fslightbox="{{ $galleryId }}"
                        data-type="image"
                        href="{{ $img['src'] }}"
                        title="Click to view full photo in gallery" 
                        style="position: relative; display: block; width: 84px; height: 84px; border-radius: 8px; overflow: hidden; border: 1.5px solid rgba(128, 128, 128, 0.25); box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08); background: #f8fafc; transition: transform 0.15s ease; cursor: pointer;"
                        onmouseover="this.style.transform='scale(1.05)'; this.style.borderColor='#2563eb';"
                        onmouseout="this.style.transform='scale(1)'; this.style.borderColor='rgba(128, 128, 128, 0.25)';"
                    >
                        <img src="{{ $img['src'] }}" style="width: 100%; height: 100%; object-fit: cover;" alt="Before Photo #{{ $img['index'] + 1 }}" />
                        <span style="position: absolute; bottom: 4px; right: 4px; font-size: 10px; font-weight: 700; background: rgba(0, 0, 0, 0.7); color: white; padding: 1px 5px; border-radius: 4px;">#{{ $img['index'] + 1 }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</div>
