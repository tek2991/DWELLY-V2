@php
    $item = $getRecord();
    $evidenceList = $item ? $item->evidence()->orderBy('display_order')->get() : collect();
@endphp

@include('livewire.operations.evidence-gallery-modal', [
    'item' => $item,
    'evidenceList' => $evidenceList
])
