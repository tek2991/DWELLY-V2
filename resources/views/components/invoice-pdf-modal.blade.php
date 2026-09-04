<div style="width: 100%; display: flex; flex-direction: column; gap: 0.75rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; background-color: #f8fafc; padding: 0.625rem 0.875rem; border-radius: 0.375rem; border: 1px solid #e2e8f0;">
        <div>
            <span style="font-size: 0.875rem; font-weight: 600; color: #0f172a;">
                Rent Demand Notice: #{{ $invoice->invoice_number }}
            </span>
            @if($invoice->contact?->name)
                <span style="font-size: 0.75rem; color: #64748b; margin-left: 0.5rem;">
                    ({{ $invoice->contact->name }})
                </span>
            @endif
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <x-filament::button
                tag="a"
                :href="route('billing.demand.pdf', ['invoice' => $invoice])"
                target="_blank"
                icon="heroicon-o-arrow-top-right-on-square"
                color="gray"
                size="sm"
                outlined
            >
                Open in New Tab
            </x-filament::button>
        </div>
    </div>

    <div style="width: 100%; height: 75vh; border: 1px solid #e2e8f0; border-radius: 0.375rem; overflow: hidden; background: #ffffff;">
        <iframe
            src="{{ route('billing.demand.pdf', ['invoice' => $invoice]) }}"
            style="width: 100%; height: 100%; border: none;"
            title="Rent Demand Notice PDF"
        ></iframe>
    </div>
</div>
