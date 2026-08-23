@php
    $ticket = $ticket ?? ($record ?? (isset($getRecord) ? $getRecord() : null));
    if (! $ticket && isset($getLivewire)) {
        $livewire = $getLivewire();
        $ticket = method_exists($livewire, 'getOwnerRecord') ? $livewire->getOwnerRecord() : ($livewire->record ?? null);
    }
    $proofs = $ticket ? $ticket->getMedia('client_acceptance_proofs') : collect();
    
    $clientInvoice = null;
    if ($ticket && ! $ticket->is_direct_vendor) {
        if ($ticket->owner_invoice_id) {
            $clientInvoice = \Tek2991\Accounting\Models\Invoice::find($ticket->owner_invoice_id);
        } elseif ($ticket->tenant_invoice_id) {
            $clientInvoice = \Tek2991\Accounting\Models\Invoice::find($ticket->tenant_invoice_id);
        }
        if (! $clientInvoice) {
            $clientInvoice = \Tek2991\Accounting\Models\Invoice::where('reference_type', \App\Domain\Maintenance\Models\MaintenanceRequest::class)
                ->where('reference_id', $ticket->id)
                ->latest()
                ->first();
        }
    }

    $vendorBills = collect();
    if ($ticket && ! $ticket->is_direct_vendor) {
        $vendorBills = \Tek2991\Accounting\Models\Bill::where('reference_type', \App\Domain\Maintenance\Models\MaintenanceRequest::class)
            ->where('reference_id', $ticket->id)
            ->get();
        if ($vendorBills->isEmpty() && $ticket->bill_id) {
            $singleBill = \Tek2991\Accounting\Models\Bill::find($ticket->bill_id);
            if ($singleBill) {
                $vendorBills = collect([$singleBill]);
            }
        }
    }
@endphp

@if($ticket && ($ticket->hasClientAcceptance() || $ticket->isWorkCompleted()))
    <div style="border-radius: 0.5rem; border: 1px solid rgba(16, 185, 129, 0.3); background: rgba(16, 185, 129, 0.04); padding: 0.875rem; font-size: 0.8125rem;">
        <!-- Header -->
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem; gap: 0.5rem; flex-wrap: wrap;">
            <div style="display: flex; align-items: center; gap: 0.375rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 1.5rem; height: 1.5rem; border-radius: 9999px; background: #10b981; color: #ffffff; font-size: 0.75rem; font-weight: 700;">
                    ✓
                </span>
                <strong style="color: #065f46; font-size: 0.875rem;">Acceptance Confirmed</strong>
            </div>
            <span style="font-size: 0.6875rem; font-weight: 700; background: rgba(16, 185, 129, 0.15); color: #047857; padding: 0.15rem 0.5rem; border-radius: 9999px; border: 1px solid rgba(16, 185, 129, 0.3);">
                Work Completed
            </span>
        </div>

        <!-- Details List -->
        <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-bottom: 0.75rem;">
            <div>
                <span style="color: #64748b; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase;">Accepted By:</span>
                <div style="font-weight: 700; color: #0f172a; margin-top: 0.125rem;">
                    {{ $ticket->client_accepted_by_name ?: 'Client' }}
                </div>
            </div>

            <div>
                <span style="color: #64748b; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase;">Date:</span>
                <div style="font-weight: 600; color: #334155; margin-top: 0.125rem;">
                    {{ $ticket->client_accepted_at?->format('d M Y') ?? ($ticket->completed_at?->format('d M Y') ?? 'Confirmed') }}
                </div>
            </div>

            @if(filled($ticket->client_acceptance_notes))
                <div>
                    <span style="color: #64748b; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase;">Remarks:</span>
                    <div style="color: #334155; font-style: italic; margin-top: 0.125rem; font-size: 0.75rem;">
                        "{{ $ticket->client_acceptance_notes }}"
                    </div>
                </div>
            @endif
        </div>

        <!-- Financial Section -->
        @if($ticket->is_direct_vendor)
            <div style="padding: 0.5rem 0.625rem; border-radius: 0.375rem; background: rgba(37, 99, 235, 0.05); border: 1px solid rgba(37, 99, 235, 0.2); margin-bottom: 0.75rem; font-size: 0.75rem;">
                <div style="font-weight: 700; color: #1e40af; margin-bottom: 0.25rem;">🛠 Direct Repair Settlement</div>
                <div style="color: #475569; font-size: 0.6875rem;">Paying party settles directly with contractor. No Dwelly invoice/bill generated.</div>
                @if(filled($ticket->direct_payment_reference))
                    <div style="margin-top: 0.25rem; color: #1e40af; font-size: 0.6875rem;">Ref: <strong>{{ $ticket->direct_payment_reference }}</strong></div>
                @endif
            </div>
        @else
            @if($clientInvoice || $vendorBills->isNotEmpty())
                <div style="padding: 0.5rem 0.625rem; border-radius: 0.375rem; background: rgba(128, 128, 128, 0.05); border: 1px solid rgba(128, 128, 128, 0.2); margin-bottom: 0.75rem; font-size: 0.75rem;">
                    @if($clientInvoice)
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.25rem;">
                            <span style="color: #1e40af; font-weight: 700;">Invoice #{{ $clientInvoice->invoice_number }}</span>
                            <span style="font-weight: 700; color: #059669;">₹{{ number_format((float) $clientInvoice->grand_total, 2) }}</span>
                        </div>
                        <a href="{{ route('billing.invoice.pdf', $clientInvoice) }}" target="_blank" style="font-size: 0.6875rem; color: #2563eb; text-decoration: underline;">
                            Download Invoice PDF &rarr;
                        </a>
                    @endif

                    @if($vendorBills->isNotEmpty())
                        <div style="margin-top: 0.375rem; padding-top: 0.375rem; border-top: 1px dashed rgba(128, 128, 128, 0.2);">
                            <span style="color: #be123c; font-weight: 700;">{{ $vendorBills->count() }} Vendor Bill(s)</span>
                        </div>
                    @endif
                </div>
            @endif
        @endif

        <!-- Documentary Proof Thumbnails -->
        @if($proofs->isNotEmpty())
            <div>
                <span style="color: #64748b; font-size: 0.6875rem; font-weight: 600; text-transform: uppercase; display: block; margin-bottom: 0.375rem;">
                    Proof Files ({{ $proofs->count() }}):
                </span>
                <div style="display: flex; flex-wrap: wrap; gap: 0.375rem;">
                    @foreach($proofs as $media)
                        @php
                            $isImage = str_starts_with($media->mime_type ?? '', 'image/');
                            $url = $media->getUrl();
                        @endphp
                        @if($isImage)
                            <a href="{{ $url }}" target="_blank" style="display: inline-block; border-radius: 0.25rem; overflow: hidden; border: 1px solid rgba(16, 185, 129, 0.4);" title="{{ $media->file_name }}">
                                <img src="{{ $url }}" alt="{{ $media->file_name }}" style="width: 54px; height: 42px; object-fit: cover; display: block;" />
                            </a>
                        @else
                            <a href="{{ $url }}" target="_blank" style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; background: #ffffff; border: 1px solid rgba(16, 185, 129, 0.4); border-radius: 0.25rem; font-size: 0.6875rem; color: #065f46; text-decoration: none;">
                                <span>📄</span>
                                <span style="max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $media->file_name }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
    </div>
@else
    <div style="padding: 0.75rem; border-radius: 0.5rem; background: rgba(128, 128, 128, 0.04); border: 1px dashed rgba(128, 128, 128, 0.25); font-size: 0.75rem; color: #64748b; line-height: 1.4;">
        ⏳ <strong>Awaiting Acceptance:</strong> Paying party sign-off will appear here once on-site work is completed and accepted in the <strong>Completion, Report & Verification</strong> tab.
    </div>
@endif
