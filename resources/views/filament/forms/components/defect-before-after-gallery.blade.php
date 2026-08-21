@php
    $record = $getRecord();
    $beforeMedia = $record ? $record->getMedia('issue_photos') : collect();
    $afterMedia = $record ? $record->getMedia('repaired_photos') : collect();

    $beforeImages = [];
    foreach ($beforeMedia as $index => $media) {
        $path = $media->getPath();
        if (file_exists($path)) {
            $mime = $media->mime_type ?: 'image/jpeg';
            $base64 = base64_encode(file_get_contents($path));
            $src = "data:{$mime};base64,{$base64}";
        } else {
            $src = $media->getUrl();
        }
        $beforeImages[] = [
            'src' => $src,
            'name' => $media->file_name ?? "Before Photo " . ($index + 1),
            'index' => $index,
        ];
    }

    $afterImages = [];
    foreach ($afterMedia as $index => $media) {
        $path = $media->getPath();
        if (file_exists($path)) {
            $mime = $media->mime_type ?: 'image/jpeg';
            $base64 = base64_encode(file_get_contents($path));
            $src = "data:{$mime};base64,{$base64}";
        } else {
            $src = $media->getUrl();
        }
        $afterImages[] = [
            'src' => $src,
            'name' => $media->file_name ?? "After Photo " . ($index + 1),
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
    class="w-full flex flex-col gap-4"
>
    <!-- Defect & Resolution Summary Header -->
    <div style="background: rgba(128, 128, 128, 0.04); border: 1px solid rgba(128, 128, 128, 0.15); border-radius: 8px; padding: 12px 16px; margin-bottom: 4px;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; margin-bottom: 6px;">
            <span style="font-size: 13px; font-weight: 600;">
                Defect: <span style="font-weight: 400;">{{ $record?->issue_description ?: 'N/A' }}</span>
            </span>
            <span style="font-size: 12px; color: #64748b;">
                Resolution: <strong style="color: #059669;">{{ $record?->repair_action ?: 'Pending Resolution' }}</strong>
            </span>
        </div>
    </div>

    <!-- Side-by-Side Before and After Gallery Boxes -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px;">
        <!-- Left Box: Before Repair Photos -->
        <div style="background: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.15); border-radius: 8px; padding: 14px 16px;">
            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #1e3a8a; letter-spacing: 0.5px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                <span style="display: flex; align-items: center; gap: 6px;">
                    <span>📸 Before Repair ({{ count($beforeImages) }})</span>
                </span>
                <span style="font-weight: 500; text-transform: none; color: #64748b; font-size: 11px;">
                    Reported Damage
                </span>
            </div>

            @if(empty($beforeImages))
                <div style="padding: 24px; border-radius: 6px; border: 1px dashed rgba(128, 128, 128, 0.25); font-size: 12px; color: #94a3b8; text-align: center;">
                    No before-repair photos recorded.
                </div>
            @else
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    @foreach($beforeImages as $img)
                        <a 
                            data-fslightbox="evidence-before-{{ $record->id }}"
                            data-type="image"
                            href="{{ $img['src'] }}"
                            title="Click to view in full gallery" 
                            style="position: relative; display: block; width: 88px; height: 88px; border-radius: 8px; overflow: hidden; border: 1.5px solid rgba(128, 128, 128, 0.25); box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08); background: #f8fafc; transition: transform 0.15s ease; cursor: pointer;"
                            onmouseover="this.style.transform='scale(1.05)'; this.style.borderColor='#2563eb';"
                            onmouseout="this.style.transform='scale(1)'; this.style.borderColor='rgba(128, 128, 128, 0.25)';"
                        >
                            <img src="{{ $img['src'] }}" style="width: 100%; height: 100%; object-fit: cover;" alt="Before Photo #{{ $img['index'] + 1 }}" />
                            <span style="position: absolute; bottom: 4px; right: 4px; font-size: 10px; font-weight: 700; background: rgba(0, 0, 0, 0.7); color: white; padding: 1px 5px; border-radius: 4px;">#{{ $img['index'] + 1 }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Right Box: After Repair Photos -->
        <div style="background: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.15); border-radius: 8px; padding: 14px 16px;">
            <div style="font-size: 12px; font-weight: 700; text-transform: uppercase; color: #065f46; letter-spacing: 0.5px; margin-bottom: 10px; display: flex; align-items: center; justify-content: space-between;">
                <span style="display: flex; align-items: center; gap: 6px;">
                    <span>🛠 After Repair ({{ count($afterImages) }})</span>
                </span>
                <span style="font-weight: 500; text-transform: none; color: #64748b; font-size: 11px;">
                    Completion Proof
                </span>
            </div>

            @if(empty($afterImages))
                <div style="padding: 24px; border-radius: 6px; border: 1px dashed rgba(128, 128, 128, 0.25); font-size: 12px; color: #94a3b8; text-align: center;">
                    ⏳ Pending repair completion & proof upload.
                </div>
            @else
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    @foreach($afterImages as $img)
                        <a 
                            data-fslightbox="evidence-after-{{ $record->id }}"
                            data-type="image"
                            href="{{ $img['src'] }}"
                            title="Click to view in full gallery" 
                            style="position: relative; display: block; width: 88px; height: 88px; border-radius: 8px; overflow: hidden; border: 1.5px solid rgba(5, 150, 105, 0.35); box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08); background: #f8fafc; transition: transform 0.15s ease; cursor: pointer;"
                            onmouseover="this.style.transform='scale(1.05)'; this.style.borderColor='#059669';"
                            onmouseout="this.style.transform='scale(1)'; this.style.borderColor='rgba(5, 150, 105, 0.35)';"
                        >
                            <img src="{{ $img['src'] }}" style="width: 100%; height: 100%; object-fit: cover;" alt="After Photo #{{ $img['index'] + 1 }}" />
                            <span style="position: absolute; bottom: 4px; right: 4px; font-size: 10px; font-weight: 700; background: rgba(5, 150, 105, 0.85); color: white; padding: 1px 5px; border-radius: 4px;">#{{ $img['index'] + 1 }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
