@php
    $ticket = $ticket ?? ($record ?? (isset($getRecord) ? $getRecord() : null));
    if (! $ticket && isset($getLivewire)) {
        $livewire = $getLivewire();
        $ticket = method_exists($livewire, 'getOwnerRecord') ? $livewire->getOwnerRecord() : ($livewire->record ?? null);
    }
    $proofs = $ticket ? $ticket->getMedia('client_acceptance_proofs') : collect();
    
    $clientInvoice = null;
    if ($ticket) {
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
    if ($ticket) {
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
    <div style="background-color: #f0fdf4; border: 1px solid #86efac; border-left: 4px solid #16a34a; border-radius: 8px; padding: 14px 16px; margin-bottom: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <span style="font-size: 18px;">✅</span>
                <div>
                    <strong style="font-size: 14px; color: #166534; display: block;">Paying Party Repair Acceptance Confirmed</strong>
                    <span style="font-size: 12px; color: #15803d;">Client has verified and signed off on the completed physical repairs.</span>
                </div>
            </div>
            <div style="font-size: 11px; background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-weight: bold; border: 1px solid #bbf7d0;">
                Status: Completed
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; background: #ffffff; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px 12px; margin-bottom: 12px;">
            <div>
                <div style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;">Accepted By:</div>
                <div style="font-size: 13px; font-weight: 700; color: #0f172a; margin-top: 2px;">
                    {{ $ticket->client_accepted_by_name ?: 'Property Owner / Tenant' }}
                </div>
            </div>
            <div>
                <div style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;">Acceptance Date:</div>
                <div style="font-size: 13px; font-weight: 600; color: #0f172a; margin-top: 2px;">
                    {{ $ticket->client_accepted_at?->format('d M Y, h:i A') ?? ($ticket->completed_at?->format('d M Y, h:i A') ?? 'Confirmed on Record') }}
                </div>
            </div>
            @if(filled($ticket->client_acceptance_notes))
            <div style="grid-column: 1 / -1;">
                <div style="font-size: 11px; color: #64748b; font-weight: 600; text-transform: uppercase;">Client Remarks / Notes:</div>
                <div style="font-size: 12px; color: #334155; margin-top: 2px; font-style: italic;">
                    "{{ $ticket->client_acceptance_notes }}"
                </div>
            </div>
            @endif
        </div>

        <!-- Generated Invoices & Bills Section -->
        <div style="margin-bottom: 12px; background: #ffffff; border: 1px solid #bbf7d0; border-radius: 6px; padding: 10px 12px;">
            <div style="font-size: 12px; font-weight: 700; color: #166534; margin-bottom: 8px;">
                💳 Financial Accounting Documents:
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 10px;">
                <!-- Client Invoice Card -->
                <div style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; background: #f8fafc;">
                    <div style="font-size: 11px; font-weight: 700; color: #1e40af; text-transform: uppercase; margin-bottom: 4px;">
                        📄 Client Invoice (Receivable)
                    </div>
                    @if($clientInvoice)
                        <div style="font-size: 12px; font-weight: 600; color: #0f172a;">
                            Invoice #{{ $clientInvoice->invoice_number }}
                            <span style="font-size: 11px; color: #16a34a; font-weight: bold; margin-left: 4px;">
                                (₹{{ number_format((float) $clientInvoice->grand_total, 2) }})
                            </span>
                        </div>
                        <div style="font-size: 11px; color: #64748b; margin-top: 2px;">
                            Billed To: {{ $clientInvoice->contact?->name ?? 'Client' }} • Status: {{ ucfirst($clientInvoice->status?->value ?? (string) $clientInvoice->status) }}
                        </div>
                        <div style="margin-top: 6px; display: flex; gap: 6px;">
                            <a
                                href="{{ route('billing.invoice.pdf', $clientInvoice) }}"
                                target="_blank"
                                style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; font-size: 11px; font-weight: 600; background: #1e40af; color: #ffffff; border-radius: 4px; text-decoration: none;"
                            >
                                <span>📥 Download Invoice PDF</span>
                            </a>
                            <a
                                href="{{ \App\Filament\Resources\Billing\MaintenanceBillingResource::getUrl('edit', ['record' => $clientInvoice]) }}"
                                target="_blank"
                                style="display: inline-flex; align-items: center; gap: 4px; padding: 3px 8px; font-size: 11px; font-weight: 600; background: #ffffff; color: #1e40af; border: 1px solid #1e40af; border-radius: 4px; text-decoration: none;"
                            >
                                <span>Open in Billing</span>
                            </a>
                        </div>
                    @else
                        <div style="font-size: 12px; color: #94a3b8; font-style: italic;">
                            Invoice not generated yet.
                        </div>
                    @endif
                </div>

                <!-- Vendor Bills Card -->
                <div style="border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; background: #f8fafc;">
                    <div style="font-size: 11px; font-weight: 700; color: #991b1b; text-transform: uppercase; margin-bottom: 4px;">
                        🧾 Vendor Bills (Payable)
                    </div>
                    @if($vendorBills->isNotEmpty())
                        @foreach($vendorBills as $bill)
                            <div style="font-size: 12px; font-weight: 600; color: #0f172a; margin-bottom: 4px;">
                                Bill #{{ $bill->bill_number }}
                                <span style="font-size: 11px; color: #dc2626; font-weight: bold; margin-left: 4px;">
                                    (₹{{ number_format((float) $bill->grand_total, 2) }})
                                </span>
                                <div style="font-size: 11px; color: #64748b;">
                                    Vendor: {{ $bill->contact?->name ?? 'Service Contractor' }}
                                </div>
                                <div style="margin-top: 4px;">
                                    <a
                                        href="{{ route('billing.bill.pdf', $bill) }}"
                                        target="_blank"
                                        style="display: inline-flex; align-items: center; gap: 4px; padding: 2px 6px; font-size: 10.5px; font-weight: 600; background: #b91c1c; color: #ffffff; border-radius: 4px; text-decoration: none;"
                                    >
                                        <span>📥 Download Bill PDF</span>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div style="font-size: 12px; color: #94a3b8; font-style: italic;">
                            No vendor bills issued.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Attached Documentary Proof Files -->
        <div>
            <div style="font-size: 12px; font-weight: 700; color: #166534; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
                <span>📎 Uploaded Documentary Proof ({{ $proofs->count() }}):</span>
            </div>

            @if($proofs->isEmpty())
                <div style="font-size: 12px; color: #64748b; font-style: italic;">
                    No document files attached. Use the <strong>"Manage Acceptance Proof"</strong> button in the header actions above to upload confirmation files.
                </div>
            @else
                <div style="display: flex; flex-wrap: wrap; gap: 10px;">
                    @foreach($proofs as $index => $media)
                        @php
                            $isImage = str_starts_with($media->mime_type ?? '', 'image/');
                            $url = $media->getUrl();
                        @endphp

                        @if($isImage)
                            <a
                                href="{{ $url }}"
                                data-fslightbox="client-acceptance-gallery"
                                style="display: inline-block; position: relative; border: 2px solid #86efac; border-radius: 6px; overflow: hidden; background: #ffffff; text-decoration: none; cursor: pointer;"
                                title="{{ $media->file_name }}"
                            >
                                <img
                                    src="{{ $url }}"
                                    alt="{{ $media->file_name }}"
                                    style="width: 130px; height: 95px; object-fit: cover; display: block;"
                                />
                                <div style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0, 0, 0, 0.6); color: #ffffff; font-size: 10px; padding: 2px 4px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    🔍 Click to View
                                </div>
                            </a>
                        @else
                            <a
                                href="{{ $url }}"
                                target="_blank"
                                style="display: inline-flex; align-items: center; gap: 6px; padding: 8px 12px; background: #ffffff; border: 1.5px solid #86efac; border-radius: 6px; color: #166534; font-size: 12px; font-weight: 600; text-decoration: none;"
                            >
                                <span>📄</span>
                                <span>{{ $media->file_name }}</span>
                                <span style="font-size: 10px; color: #64748b;">({{ number_format($media->size / 1024, 1) }} KB)</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@else
    <div style="background-color: rgba(37, 99, 235, 0.05); border: 1px dashed rgba(37, 99, 235, 0.3); border-radius: 8px; padding: 12px 16px; margin-bottom: 16px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <span style="font-size: 20px;">⏳</span>
            <div>
                <strong style="font-size: 13px; color: #1e3a8a; display: block;">Awaiting Paying Party Acceptance</strong>
                <span style="font-size: 12px; color: #475569;">When on-site repairs are completed, click <strong>"Mark Work Completed (Client Acceptance)"</strong> above to upload the signed proof sheet, WhatsApp approval, or email. Client Invoice will be autogenerated upon completion.</span>
            </div>
        </div>
    </div>
@endif
