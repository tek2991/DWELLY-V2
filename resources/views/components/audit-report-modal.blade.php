<div style="width: 100%; display: flex; flex-direction: column; gap: 0.75rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; background-color: #f8fafc; padding: 0.625rem 0.875rem; border-radius: 0.375rem; border: 1px solid #e2e8f0;">
        <div>
            <span style="font-size: 0.875rem; font-weight: 600; color: #0f172a;">
                Inspection Report: {{ $audit->audit_number }}
            </span>
            @if($audit->property?->building_name)
                <span style="font-size: 0.75rem; color: #64748b; margin-left: 0.5rem;">
                    ({{ $audit->property->building_name }})
                </span>
            @endif
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <x-filament::button
                tag="a"
                :href="route('operations.audits.pdf.download', ['audit' => $audit])"
                icon="heroicon-o-arrow-down-tray"
                color="primary"
                size="sm"
            >
                Download PDF
            </x-filament::button>
            <x-filament::button
                tag="a"
                :href="route('operations.audits.pdf', ['audit' => $audit])"
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

    <div style="width: 100%; height: 75vh; border: 1px solid #e2e8f0; border-radius: 0.375rem; overflow: hidden; background: #ffffff;">
        <iframe
            src="{{ route('operations.audits.pdf', ['audit' => $audit]) }}"
            style="width: 100%; height: 100%; border: none;"
            title="Inspection Report PDF"
        ></iframe>
    </div>
</div>
