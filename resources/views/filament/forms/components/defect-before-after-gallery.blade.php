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
    style="width: 100%; display: flex; flex-direction: column; gap: 0.75rem;"
>
    <!-- Defect & Resolution Summary Header -->
    <div style="background: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.15); border-radius: 0.75rem; padding: 0.875rem;">
        <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.5rem;">
            <span style="font-size: 0.75rem; font-weight: 600; color: inherit;">
                Defect: <span style="font-weight: 400; color: #4b5563;">{{ $record?->issue_description ?: 'N/A' }}</span>
            </span>
            <span style="font-size: 0.75rem; color: #6b7280;">
                Resolution: <strong style="color: #059669; font-weight: 700;">{{ $record?->repair_action ?: 'Pending Resolution' }}</strong>
            </span>
        </div>
    </div>

    <!-- Side-by-Side Before and After Gallery Boxes -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
        <!-- Left Box: Before Repair Photos -->
        <div style="background: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.15); border-radius: 0.75rem; padding: 0.875rem;">
            <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #1e40af; margin-bottom: 0.625rem; display: flex; align-items: center; justify-content: space-between;">
                <span style="display: flex; align-items: center; gap: 0.375rem;">
                    <span>📸 Before Repair ({{ count($beforeImages) }})</span>
                </span>
                <span style="font-weight: 400; text-transform: none; color: #6b7280; font-size: 0.75rem;">
                    Reported Damage
                </span>
            </div>

            @if(empty($beforeImages))
                <div style="padding: 1.5rem 1rem; border-radius: 0.5rem; border: 1px dashed rgba(128, 128, 128, 0.25); font-size: 0.75rem; color: #9ca3af; text-align: center;">
                    No before-repair photos recorded.
                </div>
            @else
                <div style="display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap;">
                    @foreach($beforeImages as $img)
                        <a 
                            data-fslightbox="evidence-before-{{ $record->id }}"
                            data-type="image"
                            href="{{ $img['src'] }}"
                            title="Click to view in full gallery" 
                            style="position: relative; display: block; width: 88px; height: 88px; border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(128, 128, 128, 0.2); background: rgba(128, 128, 128, 0.05); cursor: pointer; text-decoration: none;"
                        >
                            <img src="{{ $img['src'] }}" style="width: 100%; height: 100%; object-fit: cover;" alt="Before Photo #{{ $img['index'] + 1 }}" />
                            <span style="position: absolute; bottom: 4px; right: 4px; font-size: 10px; font-weight: 700; background: rgba(0, 0, 0, 0.75); color: #ffffff; padding: 2px 5px; border-radius: 3px;">#{{ $img['index'] + 1 }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <!-- Right Box: After Repair Photos -->
        <div style="background: rgba(128, 128, 128, 0.03); border: 1px solid rgba(128, 128, 128, 0.15); border-radius: 0.75rem; padding: 0.875rem;">
            <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #065f46; margin-bottom: 0.625rem; display: flex; align-items: center; justify-content: space-between;">
                <span style="display: flex; align-items: center; gap: 0.375rem;">
                    <span>🛠 After Repair ({{ count($afterImages) }})</span>
                </span>
                <span style="font-weight: 400; text-transform: none; color: #6b7280; font-size: 0.75rem;">
                    Completion Proof
                </span>
            </div>

            @if(empty($afterImages))
                <div style="padding: 1.5rem 1rem; border-radius: 0.5rem; border: 1px dashed rgba(128, 128, 128, 0.25); font-size: 0.75rem; color: #9ca3af; text-align: center;">
                    ⏳ Pending repair completion & proof upload.
                </div>
            @else
                <div style="display: flex; align-items: center; gap: 0.625rem; flex-wrap: wrap;">
                    @foreach($afterImages as $img)
                        <a 
                            data-fslightbox="evidence-after-{{ $record->id }}"
                            data-type="image"
                            href="{{ $img['src'] }}"
                            title="Click to view in full gallery" 
                            style="position: relative; display: block; width: 88px; height: 88px; border-radius: 0.5rem; overflow: hidden; border: 1px solid rgba(16, 185, 129, 0.4); background: rgba(128, 128, 128, 0.05); cursor: pointer; text-decoration: none;"
                        >
                            <img src="{{ $img['src'] }}" style="width: 100%; height: 100%; object-fit: cover;" alt="After Photo #{{ $img['index'] + 1 }}" />
                            <span style="position: absolute; bottom: 4px; right: 4px; font-size: 10px; font-weight: 700; background: #059669; color: #ffffff; padding: 2px 5px; border-radius: 3px;">#{{ $img['index'] + 1 }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
