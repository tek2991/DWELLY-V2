@php
    $isDraft = $media->collection_name === 'draft_pdf';
    $isSigned = $media->collection_name === 'signed_pdf';
    $isArchived = $media->collection_name === 'archived_signed_pdf';
    
    $version = $media->getCustomProperty('version', '?');
    $date = $media->created_at->format('M d, Y h:i A');
    
    $icon = $isSigned ? 'heroicon-m-check-badge' : 'heroicon-m-document';
    $iconColor = $isSigned ? 'text-success-500' : ($isArchived ? 'text-gray-400' : 'text-primary-500');
    $label = $isDraft ? "Draft v{$version}" : ($isSigned ? "Signed Copy" : "Archived Signed");
@endphp
<li class="flex items-center justify-between p-2 rounded-md bg-gray-50 dark:bg-white/5 border border-gray-200 dark:border-white/10">
    <div class="flex items-center gap-2">
        <x-filament::icon :icon="$icon" class="w-4 h-4 {{ $iconColor }}" />
        <a href="#" 
           wire:click.prevent="mountAction('viewHistoryPdf', { mediaId: {{ $media->id }}, title: '{{ addslashes($label) }}' })" 
           class="text-sm font-medium text-primary-600 hover:text-primary-500 hover:underline dark:text-primary-400 dark:hover:text-primary-300">
           {{ $label }}
        </a>
    </div>
    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $date }}</span>
</li>
