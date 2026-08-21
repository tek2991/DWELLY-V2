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
    <div style="display: flex; justify-content: space-between; align-items: center; background-color: #f8fafc; padding: 0.625rem 0.875rem; border-radius: 0.375rem; border: 1px solid #e2e8f0; flex-wrap: wrap; gap: 8px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <span style="font-size: 0.875rem; font-weight: 700; color: #1e3a8a;">
                📄 Maintenance Dossier: #{{ $ticket->ticket_number }}
            </span>
            @if($ticket->property?->building_name || $ticket->property?->name || $ticket->property?->code)
                <span style="font-size: 0.75rem; color: #64748b;">
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
    <div style="width: 100%; height: 75vh; border: 1px solid #e2e8f0; border-radius: 0.375rem; overflow: hidden; background: #ffffff;">
        <iframe
            src="{{ route('operations.maintenance_requests.pdf', ['record' => $ticket]) }}"
            style="width: 100%; height: 100%; border: none;"
            title="Maintenance Request PDF Dossier"
        ></iframe>
    </div>
</div>
@else
<div style="padding: 1rem; color: #64748b; font-size: 0.875rem;">
    Unable to load maintenance request preview.
</div>
@endif
