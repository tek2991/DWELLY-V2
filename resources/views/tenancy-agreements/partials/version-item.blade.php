@php
    $isPdf = str_ends_with(strtolower($media->file_name), '.pdf') || $media->mime_type === 'application/pdf';
    $isDraftPdf = $media->collection_name === 'draft_pdf';
    $isDraftWord = $media->collection_name === 'draft_word';
    $isSigned = $media->collection_name === 'signed_agreement';

    $version = $media->getCustomProperty('version', $record?->version ?? 1);
    $date = $media->created_at ? $media->created_at->format('M d, Y h:i A') : 'N/A';
    $fileSize = $media->human_readable_size ?? (round($media->size / 1024) . ' KB');

    if ($isSigned) {
        $icon = 'heroicon-m-check-badge';
        $iconHex = '#10b981';
        $label = 'Executed Signed Agreement';
        $pill = 'SIGNED';
        $pillColor = 'success';
    } elseif ($isDraftWord) {
        $icon = 'heroicon-m-document-text';
        $iconHex = '#0891b2';
        $label = "Draft Word (.docx) v{$version}";
        $pill = 'DOCX';
        $pillColor = 'info';
    } else {
        $icon = 'heroicon-m-document-arrow-down';
        $iconHex = '#2563eb';
        $label = "Draft PDF v{$version}";
        $pill = 'PDF';
        $pillColor = 'primary';
    }
@endphp

<li style="display: flex; align-items: center; justify-content: space-between; padding: 10px 14px; border-radius: 8px; border: 1px solid rgba(128, 128, 128, 0.2); background-color: rgba(128, 128, 128, 0.04); margin-bottom: 8px;">
    <div style="display: flex; align-items: center; gap: 12px; min-width: 0;">
        <x-filament::icon :icon="$icon" style="width: 22px; height: 22px; flex-shrink: 0; color: {{ $iconHex }};" />
        
        <div style="display: flex; flex-direction: column; min-width: 0; gap: 2px;">
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                @if ($isPdf)
                    <a href="#" 
                       wire:click.prevent="mountAction('viewHistoryPdf', { mediaId: {{ $media->id }}, title: '{{ addslashes($label) }}' })" 
                       style="font-size: 13px; font-weight: 600; color: #2563eb; text-decoration: none;"
                       onmouseover="this.style.textDecoration='underline'" 
                       onmouseout="this.style.textDecoration='none'">
                       {{ $label }}
                    </a>
                @else
                    <a href="#" 
                       wire:click.prevent="downloadMedia({{ $media->id }})" 
                       style="font-size: 13px; font-weight: 600; color: inherit; text-decoration: none;"
                       onmouseover="this.style.color='#2563eb'; this.style.textDecoration='underline'" 
                       onmouseout="this.style.color='inherit'; this.style.textDecoration='none'">
                       {{ $label }}
                    </a>
                @endif

                <x-filament::badge :color="$pillColor" size="xs">
                    {{ $pill }}
                </x-filament::badge>
            </div>
            
            <span style="font-size: 11px; color: #64748b;">
                {{ $date }} &bull; {{ $fileSize }}
            </span>
        </div>
    </div>

    <div style="display: flex; align-items: center; gap: 4px; flex-shrink: 0; margin-left: 12px;">
        @if ($isPdf)
            <x-filament::icon-button 
                type="button" 
                wire:click.prevent="mountAction('viewHistoryPdf', { mediaId: {{ $media->id }}, title: '{{ addslashes($label) }}' })"
                icon="heroicon-m-eye"
                color="gray"
                size="sm"
                tooltip="Preview Document" />
        @endif

        <x-filament::icon-button 
            type="button" 
            wire:click.prevent="downloadMedia({{ $media->id }})"
            icon="heroicon-m-arrow-down-tray"
            color="gray"
            size="sm"
            tooltip="Download Document" />
    </div>
</li>
