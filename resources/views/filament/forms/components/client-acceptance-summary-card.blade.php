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

    $statusLabel = $ticket?->status instanceof \App\Domain\Maintenance\Enums\MaintenanceStatus
        ? $ticket->status->getLabel()
        : ucfirst(str_replace('_', ' ', (string) ($ticket?->status ?? 'In Progress')));

    $statusVal = $ticket?->status instanceof \App\Domain\Maintenance\Enums\MaintenanceStatus
        ? $ticket->status->value
        : (string) ($ticket?->status ?? '');

    $canMarkCompleted = true;
    $disableReason = '';

    if (! $ticket) {
        $canMarkCompleted = false;
        $disableReason = 'Maintenance ticket record not found.';
    } else {
        $ticket->loadMissing('items.media');
        if (in_array($statusVal, ['closed', 'cancelled'])) {
            $canMarkCompleted = false;
            $disableReason = "Ticket is {$statusVal}. Re-open ticket to record work completion.";
        } elseif ($ticket->items->isEmpty()) {
            $canMarkCompleted = false;
            $disableReason = 'No defect items are recorded on this maintenance ticket.';
        } else {
            $incompleteCount = 0;
            foreach ($ticket->items as $item) {
                $hasPhotos = $item->hasMedia('repaired_photos');
                $hasAction = filled($item->repair_action);
                if (! $hasPhotos || ! $hasAction) {
                    $incompleteCount++;
                }
            }

            if ($incompleteCount > 0) {
                $canMarkCompleted = false;
                $disableReason = "{$incompleteCount} defect item(s) pending after-repair photos & resolution notes in 'Repair Execution & Completion' tab.";
            }
        }
    }

    $hasAudit = filled($ticket?->triggered_audit_id) || (bool) $ticket?->triggeredAudit;
    $auditNumber = $ticket?->triggeredAudit?->audit_number ?? ('AUD-' . ($ticket?->triggered_audit_id ?? ''));
    $canTriggerAudit = ! $hasAudit && ! in_array($statusVal, ['closed', 'cancelled']);
    $auditTooltip = $hasAudit
        ? "Quality Verification Audit #{$auditNumber} is already initiated and active for this ticket."
        : "Initiate an optional on-site inspection for quality inspectors to audit completed repairs.";

    $pdfTooltip = "Generate and preview the complete maintenance dossier PDF with defect photos, quotes, and resolution logs.";
@endphp

@if($ticket && ($ticket->hasClientAcceptance() || $ticket->isWorkCompleted()))
    <div style="border-radius: 0.875rem; border: 1.5px solid #86efac; border-left: 6px solid #10b981; background: linear-gradient(135deg, #f0fdf4 0%, #f8fafc 100%); padding: 1.25rem 1.5rem; margin-bottom: 2.25rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem; width: 100%;">
            <div style="display: flex; align-items: center; gap: 0.625rem;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 0.5rem; background: #10b981; color: #ffffff; font-size: 1.25rem; flex-shrink: 0;">
                    ✓
                </span>
                <div>
                    <strong style="font-size: 1rem; font-weight: 700; color: #065f46; display: block;">
                        Paying Party Repair Acceptance Confirmed
                    </strong>
                    <span style="font-size: 0.8125rem; color: #047857;">
                        Client has verified physical repairs on-site and confirmed satisfactory sign-off.
                    </span>
                </div>
            </div>
            <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                @if($canTriggerAudit)
                    <button
                        type="button"
                        wire:click.prevent="mountTableAction('triggerOptionalAudit')"
                        title="{{ $auditTooltip }}"
                        style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600; background: #ffffff; color: #2563eb; border: 1.5px solid #2563eb; border-radius: 0.375rem; cursor: pointer; box-shadow: 0 1px 2px rgba(37, 99, 235, 0.08);"
                    >
                        <svg style="width: 0.875rem; height: 0.875rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span>Trigger Quality Audit (Optional)</span>
                    </button>
                @elseif($hasAudit)
                    <div style="display: inline-flex; position: relative;" title="{{ $auditTooltip }}">
                        <button
                            type="button"
                            disabled
                            title="{{ $auditTooltip }}"
                            style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600; background-color: #f1f5f9; color: #0284c7; border: 1.5px solid #bae6fd; border-radius: 0.375rem; cursor: default;"
                        >
                            <svg style="width: 0.875rem; height: 0.875rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                            <span>Audit #{{ $auditNumber }} Active</span>
                        </button>
                    </div>
                @endif

                <button
                    type="button"
                    wire:click.prevent="mountTableAction('viewPdfDossier')"
                    title="{{ $pdfTooltip }}"
                    style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; font-size: 0.75rem; font-weight: 600; background: #ffffff; color: #475569; border: 1px solid #cbd5e1; border-radius: 0.375rem; cursor: pointer; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);"
                >
                    <span>📄 View Maintenance PDF</span>
                </button>
                <div style="font-size: 0.75rem; background: rgba(16, 185, 129, 0.15); color: #065f46; padding: 0.35rem 0.75rem; border-radius: 0.375rem; font-weight: 700; border: 1px solid rgba(16, 185, 129, 0.3);">
                    Status: Work Completed
                </div>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.875rem; background: #ffffff; border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem;">
            <div>
                <div style="font-size: 0.6875rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Accepted By:</div>
                <div style="font-size: 0.9375rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">
                    {{ $ticket->client_accepted_by_name ?: 'Property Owner / Tenant' }}
                </div>
            </div>
            <div>
                <div style="font-size: 0.6875rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Acceptance Date:</div>
                <div style="font-size: 0.9375rem; font-weight: 600; color: #0f172a; margin-top: 0.25rem;">
                    {{ $ticket->client_accepted_at?->format('d M Y') ?? ($ticket->completed_at?->format('d M Y') ?? 'Confirmed on Record') }}
                </div>
            </div>
            @if(filled($ticket->client_acceptance_notes))
            <div style="grid-column: 1 / -1; border-top: 1px solid rgba(16, 185, 129, 0.15); padding-top: 0.625rem; margin-top: 0.25rem;">
                <div style="font-size: 0.6875rem; color: #64748b; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">Client Remarks / Notes:</div>
                <div style="font-size: 0.8125rem; color: #334155; margin-top: 0.25rem; font-style: italic;">
                    "{{ $ticket->client_acceptance_notes }}"
                </div>
            </div>
            @endif
        </div>

        <!-- Generated Invoices & Bills Section -->
        <div style="margin-bottom: 1rem; background: #ffffff; border: 1px solid rgba(16, 185, 129, 0.25); border-radius: 0.5rem; padding: 1rem;">
            <div style="font-size: 0.8125rem; font-weight: 700; color: #065f46; margin-bottom: 0.75rem; display: flex; align-items: center; gap: 0.375rem;">
                <span>💳</span>
                <span>Financial Accounting Documents (Ledger Synchronization):</span>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                <!-- Client Invoice Card -->
                <div style="border: 1.5px solid {{ $clientInvoice ? 'rgba(37, 99, 235, 0.3)' : 'rgba(37, 99, 235, 0.4)' }}; border-radius: 0.5rem; padding: 1rem; background: {{ $clientInvoice ? 'rgba(37, 99, 235, 0.02)' : 'rgba(37, 99, 235, 0.04)' }};">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: #1e40af; text-transform: uppercase; letter-spacing: 0.05em;">
                            📄 Client Invoice (Receivable)
                        </span>
                        @if($clientInvoice)
                            <span style="font-size: 0.6875rem; background: #dbeafe; color: #1e40af; padding: 0.15rem 0.5rem; border-radius: 9999px; font-weight: 700;">
                                Issued
                            </span>
                        @endif
                    </div>
                    @if($clientInvoice)
                        <div style="font-size: 0.875rem; font-weight: 700; color: #0f172a;">
                            Invoice #{{ $clientInvoice->invoice_number }}
                            <span style="font-size: 0.8125rem; color: #059669; font-weight: 700; margin-left: 0.25rem;">
                                (₹{{ number_format((float) $clientInvoice->grand_total, 2) }})
                            </span>
                        </div>
                        <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                            Billed To: <strong>{{ $clientInvoice->contact?->name ?? 'Client' }}</strong> • Status: {{ ucfirst($clientInvoice->status?->value ?? (string) $clientInvoice->status) }}
                        </div>
                        <div style="margin-top: 0.75rem; display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            <a
                                href="{{ route('billing.invoice.pdf', $clientInvoice) }}"
                                target="_blank"
                                style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.35rem 0.625rem; font-size: 0.75rem; font-weight: 700; background-color: #2563eb; color: #ffffff; border-radius: 0.375rem; text-decoration: none;"
                            >
                                <span>📥 Download PDF</span>
                            </a>
                            <a
                                href="{{ \App\Filament\Resources\Billing\MaintenanceBillingResource::getUrl('edit', ['record' => $clientInvoice]) }}"
                                target="_blank"
                                style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.35rem 0.625rem; font-size: 0.75rem; font-weight: 600; background-color: #ffffff; color: #2563eb; border: 1px solid rgba(37, 99, 235, 0.4); border-radius: 0.375rem; text-decoration: none;"
                            >
                                <span>Open in Billing</span>
                            </a>
                        </div>
                    @else
                        <div style="font-size: 0.8125rem; color: #1e3a8a; margin-bottom: 0.75rem;">
                            Work is completed and verified. Issue the official client invoice to sync receivables.
                        </div>
                        <button
                            type="button"
                            wire:click.prevent="mountTableAction('generateClientInvoice')"
                            style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.875rem; font-size: 0.8125rem; font-weight: 700; background-color: #2563eb; color: #ffffff; border-radius: 0.375rem; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"
                        >
                            <span>⚡ Generate Client Invoice</span>
                        </button>
                    @endif
                </div>

                <!-- Vendor Bills Card -->
                <div style="border: 1.5px solid {{ $vendorBills->isNotEmpty() ? 'rgba(225, 29, 72, 0.3)' : 'rgba(225, 29, 72, 0.4)' }}; border-radius: 0.5rem; padding: 1rem; background: {{ $vendorBills->isNotEmpty() ? 'rgba(225, 29, 72, 0.02)' : 'rgba(225, 29, 72, 0.04)' }};">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                        <span style="font-size: 0.75rem; font-weight: 700; color: #be123c; text-transform: uppercase; letter-spacing: 0.05em;">
                            🧾 Vendor Bills (Payable)
                        </span>
                        @if($vendorBills->isNotEmpty())
                            <span style="font-size: 0.6875rem; background: #ffe4e6; color: #be123c; padding: 0.15rem 0.5rem; border-radius: 9999px; font-weight: 700;">
                                {{ $vendorBills->count() }} Issued
                            </span>
                        @endif
                    </div>
                    @if($vendorBills->isNotEmpty())
                        @foreach($vendorBills as $bill)
                            <div style="font-size: 0.8125rem; font-weight: 700; color: #0f172a; margin-bottom: 0.5rem; padding-bottom: 0.5rem; border-bottom: 1px solid rgba(225, 29, 72, 0.15);">
                                Bill #{{ $bill->bill_number }}
                                <span style="font-size: 0.75rem; color: #be123c; font-weight: 700; margin-left: 0.25rem;">
                                    (₹{{ number_format((float) $bill->grand_total, 2) }})
                                </span>
                                <div style="font-size: 0.75rem; color: #64748b; font-weight: 400; margin-top: 0.125rem;">
                                    Vendor: <strong>{{ $bill->contact?->name ?? 'Contractor' }}</strong>
                                </div>
                                <div style="margin-top: 0.375rem;">
                                    <a
                                        href="{{ route('billing.bill.pdf', $bill) }}"
                                        target="_blank"
                                        style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.25rem 0.5rem; font-size: 0.6875rem; font-weight: 700; background-color: #e11d48; color: #ffffff; border-radius: 0.25rem; text-decoration: none;"
                                    >
                                        <span>📥 Download Bill PDF</span>
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    @else
                        @php
                            $unbilledQuotes = $ticket ? $ticket->vendorQuotes()->whereNull('bill_id')->count() : 0;
                        @endphp
                        @if($ticket && !$ticket->is_direct_vendor && ($unbilledQuotes > 0 || (empty($ticket->bill_id) && $ticket->vendor_party_id)))
                            <div style="font-size: 0.8125rem; color: #9f1239; margin-bottom: 0.75rem;">
                                Work is completed. Generate payable trade contractor bills for accounting.
                            </div>
                            <button
                                type="button"
                                wire:click.prevent="mountTableAction('generateVendorBills')"
                                style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.875rem; font-size: 0.8125rem; font-weight: 700; background-color: #e11d48; color: #ffffff; border-radius: 0.375rem; border: none; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"
                            >
                                <span>⚡ Generate Vendor Bills</span>
                            </button>
                        @else
                            <div style="font-size: 0.75rem; color: #94a3b8; font-style: italic;">
                                {{ ($ticket && $ticket->is_direct_vendor) ? 'Direct Client Payment (No Dwelly Bills)' : 'No vendor bills required / recorded.' }}
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <!-- Attached Documentary Proof Files -->
        <div>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.625rem;">
                <div style="font-size: 0.8125rem; font-weight: 700; color: #065f46; display: flex; align-items: center; gap: 0.375rem;">
                    <span>📎</span>
                    <span>Uploaded Documentary Proof ({{ $proofs->count() }}):</span>
                </div>
                <button
                    type="button"
                    wire:click.prevent="mountTableAction('manageClientAcceptance')"
                    style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.35rem 0.625rem; font-size: 0.75rem; font-weight: 600; background: #ffffff; color: #065f46; border: 1px solid rgba(16, 185, 129, 0.4); border-radius: 0.375rem; cursor: pointer;"
                >
                    <span>📎 Manage Acceptance Proof</span>
                </button>
            </div>

            @if($proofs->isEmpty())
                <div style="font-size: 0.75rem; color: #64748b; font-style: italic; background: #ffffff; border: 1px dashed rgba(16, 185, 129, 0.3); border-radius: 0.5rem; padding: 0.75rem;">
                    No document files attached. Click <strong>"Manage Acceptance Proof"</strong> above to upload signed sheets or chat screenshots.
                </div>
            @else
                <div style="display: flex; flex-wrap: wrap; gap: 0.625rem;">
                    @foreach($proofs as $index => $media)
                        @php
                            $isImage = str_starts_with($media->mime_type ?? '', 'image/');
                            $url = $media->getUrl();
                        @endphp

                        @if($isImage)
                            <a
                                href="{{ $url }}"
                                data-fslightbox="client-acceptance-gallery"
                                style="display: inline-block; position: relative; border: 2px solid rgba(16, 185, 129, 0.4); border-radius: 0.5rem; overflow: hidden; background: #ffffff; text-decoration: none;"
                                title="{{ $media->file_name }}"
                            >
                                <img
                                    src="{{ $url }}"
                                    alt="{{ $media->file_name }}"
                                    style="width: 130px; height: 95px; object-fit: cover; display: block;"
                                />
                                <div style="position: absolute; bottom: 0; left: 0; right: 0; background: rgba(0, 0, 0, 0.65); color: #ffffff; font-size: 10px; padding: 2px 4px; text-align: center; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                    🔍 Click to View
                                </div>
                            </a>
                        @else
                            <a
                                href="{{ $url }}"
                                target="_blank"
                                style="display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.5rem 0.75rem; background: #ffffff; border: 1px solid rgba(16, 185, 129, 0.4); border-radius: 0.5rem; color: #065f46; font-size: 0.75rem; font-weight: 600; text-decoration: none;"
                            >
                                <span>📄</span>
                                <span style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 200px;">{{ $media->file_name }}</span>
                                <span style="font-size: 10px; color: #64748b;">({{ number_format($media->size / 1024, 1) }} KB)</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@else
    <!-- High-Visibility Pending Action Banner & Command Center -->
    <div style="border-radius: 0.875rem; border: 1.5px solid #93c5fd; border-left: 6px solid #2563eb; background: linear-gradient(135deg, #eff6ff 0%, #ffffff 100%); padding: 1.375rem 1.625rem; margin-bottom: 2.25rem; box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.08);">
        <div style="display: flex; align-items: flex-start; justify-content: space-between; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem; width: 100%;">
            <div style="display: flex; align-items: flex-start; gap: 0.875rem; width: 100%;">
                <span style="display: inline-flex; align-items: center; justify-content: center; width: 2.75rem; height: 2.75rem; border-radius: 0.625rem; background: #2563eb; color: #ffffff; font-size: 1.375rem; flex-shrink: 0; box-shadow: 0 2px 4px rgba(37, 99, 235, 0.3);">
                    ⏳
                </span>
                <div style="flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
                        <strong style="font-size: 1.0625rem; font-weight: 800; color: #1e3a8a;">
                            Awaiting Paying Party Acceptance &amp; Sign-Off
                        </strong>
                        <span style="font-size: 0.6875rem; font-weight: 700; background: #dbeafe; color: #1e40af; padding: 0.2rem 0.625rem; border-radius: 9999px; border: 1px solid #bfdbfe;">
                            {{ $statusLabel }}
                        </span>
                    </div>
                    <div style="font-size: 0.875rem; color: #334155; margin-top: 0.375rem; line-height: 1.5;">
                        When physical contractor repairs are finished on-site, record client acceptance to verify quality and complete this ticket. Recording acceptance will immediately unlock <strong>Client Invoice</strong> and <strong>Vendor Bills</strong> generation.
                    </div>
                </div>
            </div>
        </div>

        <!-- Prominent Direct Actions Row -->
        <div style="display: flex; align-items: center; flex-wrap: wrap; gap: 0.75rem; padding-top: 1rem; border-top: 1px solid rgba(37, 99, 235, 0.18);">
            @if($canMarkCompleted)
                <button
                    type="button"
                    wire:click.prevent="mountTableAction('recordClientAcceptanceAndComplete')"
                    title="Upload paying party acceptance proof and mark this ticket completed."
                    style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; font-size: 0.875rem; font-weight: 700; background-color: #059669; color: #ffffff; border-radius: 0.5rem; border: none; cursor: pointer; box-shadow: 0 2px 4px rgba(5, 150, 105, 0.3); transition: all 0.15s;"
                >
                    <svg style="width: 1.125rem; height: 1.125rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                    <span>Mark Work Completed (Client Acceptance)</span>
                </button>
            @else
                <div style="display: inline-flex; position: relative;" title="{{ $disableReason }}">
                    <button
                        type="button"
                        disabled
                        title="{{ $disableReason }}"
                        style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.25rem; font-size: 0.875rem; font-weight: 700; background-color: #94a3b8; color: #f8fafc; border-radius: 0.5rem; border: none; cursor: not-allowed; opacity: 0.75;"
                    >
                        <svg style="width: 1.125rem; height: 1.125rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                        <span>Mark Work Completed (Client Acceptance)</span>
                    </button>
                </div>
            @endif

            @if($canTriggerAudit)
                <button
                    type="button"
                    wire:click.prevent="mountTableAction('triggerOptionalAudit')"
                    title="{{ $auditTooltip }}"
                    style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.125rem; font-size: 0.8125rem; font-weight: 600; background-color: #ffffff; color: #2563eb; border: 1.5px solid #2563eb; border-radius: 0.5rem; cursor: pointer; box-shadow: 0 1px 2px rgba(37, 99, 235, 0.08);"
                >
                    <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                    <span>Trigger Quality Audit (Optional)</span>
                </button>
            @elseif($hasAudit)
                <div style="display: inline-flex; position: relative;" title="{{ $auditTooltip }}">
                    <button
                        type="button"
                        disabled
                        title="{{ $auditTooltip }}"
                        style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.125rem; font-size: 0.8125rem; font-weight: 600; background-color: #f1f5f9; color: #0284c7; border: 1.5px solid #bae6fd; border-radius: 0.5rem; cursor: default;"
                    >
                        <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                        <span>Audit #{{ $auditNumber }} Active</span>
                    </button>
                </div>
            @endif

            <button
                type="button"
                wire:click.prevent="mountTableAction('viewPdfDossier')"
                title="{{ $pdfTooltip }}"
                style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1.125rem; font-size: 0.8125rem; font-weight: 600; background-color: #ffffff; color: #475569; border: 1.5px solid #cbd5e1; border-radius: 0.5rem; cursor: pointer; box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);"
            >
                <svg style="width: 1rem; height: 1rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                <span>View Maintenance PDF</span>
            </button>
        </div>

        @if(! $canMarkCompleted)
            <div style="margin-top: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.5rem; background: #fffbeb; border: 1px solid #fde68a; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; font-size: 0.8125rem; color: #92400e;">
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <span style="font-size: 1rem;">⚠️</span>
                    <span><strong>Action Required:</strong> {{ $disableReason }}</span>
                </div>
                @if($ticket)
                    <a
                        href="{{ \App\Filament\Resources\Operations\MaintenanceRequestResource::getUrl('edit', ['record' => $ticket, 'relation' => 1]) }}"
                        style="display: inline-flex; align-items: center; gap: 0.25rem; padding: 0.35rem 0.75rem; font-size: 0.75rem; font-weight: 700; background: #f59e0b; color: #ffffff; border-radius: 0.375rem; text-decoration: none; white-space: nowrap; box-shadow: 0 1px 2px rgba(0,0,0,0.1);"
                    >
                        <span>Go to Repair Execution Tab →</span>
                    </a>
                @endif
            </div>
        @endif

        <!-- 3-Step Lifecycle Transition Breadcrumb -->
        <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.75rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(37, 99, 235, 0.15);">
            <div style="padding: 0.625rem 0.875rem; border-radius: 0.5rem; background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.25); font-size: 0.75rem;">
                <span style="font-weight: 700; color: #047857;">Step 1: Work Order Issued</span><br>
                <span style="color: #065f46; font-size: 0.6875rem;">Contractors authorized &amp; executing repair</span>
            </div>
            <div style="padding: 0.625rem 0.875rem; border-radius: 0.5rem; background: rgba(37, 99, 235, 0.1); border: 1px solid rgba(37, 99, 235, 0.25); font-size: 0.75rem;">
                <span style="font-weight: 700; color: #1e40af;">Step 2: Client Sign-Off (Current)</span><br>
                <span style="color: #1e3a8a; font-size: 0.6875rem;">Upload acceptance to complete ticket</span>
            </div>
            <div style="padding: 0.625rem 0.875rem; border-radius: 0.5rem; background: rgba(128, 128, 128, 0.08); border: 1px solid rgba(128, 128, 128, 0.2); font-size: 0.75rem;">
                <span style="font-weight: 700; color: #64748b;">Step 3: Invoicing &amp; Billing</span><br>
                <span style="color: #64748b; font-size: 0.6875rem;">Unlocked upon client sign-off</span>
            </div>
        </div>
    </div>
@endif

