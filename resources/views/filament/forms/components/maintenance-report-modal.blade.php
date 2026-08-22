@php
    $ticket = $ticket ?? ($record ?? (isset($getRecord) ? $getRecord() : null));
    if (! $ticket && isset($getLivewire)) {
        $livewire = $getLivewire();
        $ticket = method_exists($livewire, 'getOwnerRecord') ? $livewire->getOwnerRecord() : ($livewire->record ?? null);
    }
@endphp

@if($ticket)
<div style="width: 100%; display: flex; flex-direction: column; gap: 0.75rem;">
    <!-- Top Action Bar inside Modal -->
    <div style="display: flex; justify-content: space-between; align-items: center; background: rgba(128, 128, 128, 0.04); padding: 0.75rem 1rem; border-radius: 0.5rem; border: 1px solid rgba(128, 128, 128, 0.15); flex-wrap: wrap; gap: 0.5rem;">
        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <span style="font-size: 0.875rem; font-weight: 700; color: inherit;">
                📄 Maintenance Dossier: #{{ $ticket->ticket_number }}
            </span>
            @if($ticket->property?->building_name || $ticket->property?->name || $ticket->property?->code)
                <span style="font-size: 0.75rem; color: #6b7280;">
                    ({{ ($ticket->property->code ? '[' . $ticket->property->code . '] ' : '') . ($ticket->property->building_name ?: $ticket->property->name) }})
                </span>
            @endif
        </div>
        <div style="display: flex; gap: 0.5rem; align-items: center;">
            <x-filament::button
                tag="a"
                :href="route('operations.maintenance_requests.pdf.download', ['record' => $ticket])"
                icon="heroicon-o-arrow-down-tray"
                color="primary"
                size="sm"
            >
                Download PDF
            </x-filament::button>
            <x-filament::button
                tag="a"
                :href="route('operations.maintenance_requests.pdf', ['record' => $ticket])"
                target="_blank"
                icon="heroicon-o-arrow-top-right-on-square"
                color="gray"
                size="sm"
                outlined
            >
                Open in Tab
            </x-filament::button>
        </div>
    </div>

    <!-- Embedded PDF Viewer -->
    <div style="width: 100%; height: 75vh; border: 1px solid rgba(128, 128, 128, 0.2); border-radius: 0.5rem; overflow: hidden; background: inherit; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
        <iframe
            src="{{ route('operations.maintenance_requests.pdf', ['record' => $ticket]) }}"
            style="width: 100%; height: 100%; border: none;"
            title="Maintenance Request PDF Dossier"
        ></iframe>
    </div>
</div>
@else
<div style="padding: 1rem; color: #6b7280; font-size: 0.875rem;">
    Unable to load maintenance request preview.
</div>
@endif
