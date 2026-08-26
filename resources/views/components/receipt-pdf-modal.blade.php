@php
    $payment = $payment ?? $invoice->payments()->latest()->first();
@endphp
<div style="width: 100%; display: flex; flex-direction: column; gap: 0.75rem;">
    <div style="display: flex; justify-content: space-between; align-items: center; background-color: #f8fafc; padding: 0.625rem 0.875rem; border-radius: 0.375rem; border: 1px solid #e2e8f0;">
        <div>
            <span style="font-size: 0.875rem; font-weight: 600; color: #0f172a;">
                Payment Receipt: {{ $invoice->invoice_number }}
            </span>
            @if($invoice->contact?->name)
                <span style="font-size: 0.75rem; color: #64748b; margin-left: 0.5rem;">
                    ({{ $invoice->contact->name }})
                </span>
            @endif
        </div>
        @if($payment)
            <div style="display: flex; gap: 0.5rem;">
                <x-filament::button
                    tag="a"
                    :href="route('billing.receipt.pdf', ['invoice' => $invoice->id, 'payment' => $payment->id])"
                    target="_blank"
                    icon="heroicon-o-arrow-top-right-on-square"
                    color="gray"
                    size="sm"
                    outlined
                >
                    Open in New Tab
                </x-filament::button>
            </div>
        @endif
    </div>

    <div style="width: 100%; height: 75vh; border: 1px solid #e2e8f0; border-radius: 0.375rem; overflow: hidden; background: #ffffff;">
        @if($payment)
            <iframe
                src="{{ route('billing.receipt.pdf', ['invoice' => $invoice->id, 'payment' => $payment->id]) }}"
                style="width: 100%; height: 100%; border: none;"
                title="Payment Receipt PDF"
            ></iframe>
        @else
            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: #64748b;">
                No payment recorded yet.
            </div>
        @endif
    </div>
</div>
