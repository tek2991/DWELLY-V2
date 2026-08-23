@php
    /** @var \App\Domain\Maintenance\Models\MaintenanceRequest|null $ticket */
    if (! $ticket) return;

    $ticket->loadMissing([
        'owner',
        'tenant',
        'property',
        'ownerInvoice',
        'tenantInvoice',
        'bill',
        'vendorQuotes.vendor.vendorProfile.trade',
        'vendorQuotes.bill',
        'currentClientQuote',
    ]);

    $isDwelly = (bool) ($ticket->payer_type?->isDwellyAbsorbed() ?? false);
    $isDirect = (bool) ($ticket->is_direct_vendor ?? false);
    $payerVal = $ticket->payer_type instanceof \App\Domain\Maintenance\Enums\PayerType
        ? $ticket->payer_type->value
        : (string) ($ticket->payer_type ?? 'owner');
    $payerLabel = $ticket->payer_type?->getLabel() ?? ucfirst((string) ($ticket->payer_type ?? 'N/A'));

    $payerName = match ($payerVal) {
        'owner' => $ticket->owner?->display_name ?: ($ticket->owner?->name ?: 'Property Owner'),
        'tenant' => $ticket->tenant?->display_name ?: ($ticket->tenant?->name ?: 'Tenant'),
        'dwelly' => 'Dwelly Operations (Absorbed)',
        default => 'Paying Party',
    };

    // Client Invoice
    $invoice = $ticket->ownerInvoice ?? $ticket->tenantInvoice;
    $clientInvoiceTotal = $invoice ? (float) $invoice->grand_total : ($isDwelly || $isDirect ? 0.00 : (float) ($ticket->total_client_cost ?: $ticket->total_cost));
    $invoiceNumber = $invoice?->invoice_number;
    $invoiceStatus = $invoice?->status instanceof \BackedEnum ? $invoice->status->value : (string) ($invoice?->status ?? '');

    // Vendor Quotes & Bills
    $vendorQuotes = $ticket->vendorQuotes;
    $awardedOrBilledQuotes = $vendorQuotes->filter(fn ($q) => (bool) $q->is_awarded || ! empty($q->bill_id) || ! empty($q->work_order_number));
    if ($awardedOrBilledQuotes->isEmpty() && $vendorQuotes->isNotEmpty()) {
        $awardedOrBilledQuotes = $vendorQuotes;
    }
    $totalVendorCost = (float) $awardedOrBilledQuotes->sum('quoted_cost');
    $billedQuotes = $awardedOrBilledQuotes->filter(fn ($q) => ! empty($q->bill_id));
    $billedCount = $billedQuotes->count();
    $totalQuotesCount = $awardedOrBilledQuotes->count();
    $allBilled = $totalQuotesCount > 0 && $billedCount === $totalQuotesCount;

    // Financial Net / Margin
    $marginAmount = $clientInvoiceTotal - $totalVendorCost;
    $marginPct = $clientInvoiceTotal > 0 ? round(($marginAmount / $clientInvoiceTotal) * 100, 1) : 0;
@endphp

<div style="background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(8px); border: 1px solid rgba(128, 128, 128, 0.18); border-radius: 0.875rem; padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
    {{-- Header title bar --}}
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 0.75rem; border-bottom: 1px solid rgba(128, 128, 128, 0.12); padding-bottom: 0.75rem;">
        <div style="display: flex; align-items: center; gap: 0.625rem;">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 1.875rem; height: 1.875rem; border-radius: 0.375rem; background: rgba(37, 99, 235, 0.1); color: #2563eb; font-size: 1rem;">
                💳
            </div>
            <div>
                <h4 style="font-weight: 700; font-size: 0.875rem; color: #111827; margin: 0; line-height: 1.2;">Financial Quick Reference</h4>
                <p style="font-size: 0.6875rem; color: #6b7280; margin: 0.125rem 0 0 0;">Billing & accounting status for Ticket #{{ $ticket->ticket_number }}</p>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            @if($isDwelly)
                <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; font-weight: 700; background: rgba(16, 185, 129, 0.12); color: #047857; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid rgba(16, 185, 129, 0.25);">
                    🏢 100% Absorbed by Dwelly
                </span>
            @elseif($isDirect)
                <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; font-weight: 700; background: rgba(100, 116, 139, 0.12); color: #334155; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid rgba(100, 116, 139, 0.25);">
                    🤝 Direct Repair (Dwelly Audits Only)
                </span>
            @else
                <span style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; font-weight: 700; background: rgba(37, 99, 235, 0.12); color: #1d4ed8; padding: 0.25rem 0.625rem; border-radius: 9999px; border: 1px solid rgba(37, 99, 235, 0.25);">
                    💼 Billed to {{ $payerLabel }}
                </span>
            @endif

            <a href="{{ \App\Filament\Resources\Billing\MaintenanceBillingResource::getUrl('index') }}"
               target="_blank"
               style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.6875rem; font-weight: 600; color: #2563eb; text-decoration: none; padding: 0.25rem 0.5rem; border-radius: 0.25rem; background: rgba(37, 99, 235, 0.08);"
               title="View all invoices & bills in accounting module">
                <span>Invoices &amp; Bills</span>
                <svg style="width: 0.75rem; height: 0.75rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
            </a>
        </div>
    </div>

    {{-- Cards Grid (3 Columns) --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
        
        {{-- Card 1: Client Invoice (Receivable) --}}
        <div style="background: rgba(128, 128, 128, 0.02); border: 1px solid rgba(128, 128, 128, 0.15); border-left: 4px solid {{ $invoice ? '#10b981' : ($isDwelly || $isDirect ? '#94a3b8' : '#3b82f6') }}; border-radius: 0.625rem; padding: 0.875rem 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.625rem;">
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.375rem;">
                    <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280;">Client Invoice (Receivable)</span>
                    @if($invoice)
                        <span style="font-size: 0.6875rem; font-weight: 700; color: #047857; background: rgba(16, 185, 129, 0.12); padding: 0.125rem 0.5rem; border-radius: 0.25rem;">
                            {{ ucfirst($invoiceStatus ?: 'Issued') }}
                        </span>
                    @elseif($isDwelly)
                        <span style="font-size: 0.6875rem; font-weight: 700; color: #047857; background: rgba(16, 185, 129, 0.1); padding: 0.125rem 0.5rem; border-radius: 0.25rem;">
                            Absorbed
                        </span>
                    @elseif($isDirect)
                        <span style="font-size: 0.6875rem; font-weight: 700; color: #475569; background: rgba(100, 116, 139, 0.1); padding: 0.125rem 0.5rem; border-radius: 0.25rem;">
                            Direct
                        </span>
                    @else
                        <span style="font-size: 0.6875rem; font-weight: 700; color: #b45309; background: rgba(245, 158, 11, 0.1); padding: 0.125rem 0.5rem; border-radius: 0.25rem;">
                            Not Generated
                        </span>
                    @endif
                </div>

                <div style="display: flex; align-items: baseline; gap: 0.5rem; flex-wrap: wrap;">
                    <span style="font-size: 1.375rem; font-weight: 800; color: {{ $isDwelly ? '#059669' : ($invoice ? '#111827' : '#1e40af') }}; letter-spacing: -0.02em;">
                        ₹{{ number_format($clientInvoiceTotal, 2) }}
                    </span>
                    @if($invoice)
                        <a href="{{ route('billing.invoice.pdf', $invoice) }}"
                           target="_blank"
                           style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.75rem; font-weight: 700; font-family: monospace; color: #2563eb; text-decoration: underline;"
                           title="Download Invoice PDF">
                            <span>{{ $invoiceNumber }}</span>
                            <svg style="width: 0.75rem; height: 0.75rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                        </a>
                    @endif
                </div>

                <div style="font-size: 0.75rem; color: #4b5563; margin-top: 0.375rem; line-height: 1.4;">
                    @if($invoice)
                        <div><strong>Party:</strong> {{ $payerName }}</div>
                        <div style="font-size: 0.6875rem; color: #6b7280;">Issued: {{ $invoice->issue_date?->format('d M Y') ?? '—' }} | Due: {{ $invoice->due_date?->format('d M Y') ?? '—' }}</div>
                    @elseif($isDwelly)
                        <div>Cost is 100% absorbed by Dwelly. No client invoice issued.</div>
                    @elseif($isDirect)
                        <div>Client contracted directly. No Dwelly invoice issued.</div>
                    @else
                        <div><strong>Target Party:</strong> {{ $payerName }}</div>
                        <div style="font-size: 0.6875rem; color: #92400e;">Click "Generate Client Invoice" above once work is verified.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 2: Contractor Vendor Bills (Payable) --}}
        <div style="background: rgba(128, 128, 128, 0.02); border: 1px solid rgba(128, 128, 128, 0.15); border-left: 4px solid {{ $allBilled ? '#10b981' : ($totalQuotesCount > 0 ? '#f59e0b' : '#94a3b8') }}; border-radius: 0.625rem; padding: 0.875rem 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.625rem;">
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.375rem;">
                    <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280;">Contractor Bills (Payable)</span>
                    @if($totalQuotesCount === 0)
                        <span style="font-size: 0.6875rem; font-weight: 600; color: #6b7280; background: rgba(128, 128, 128, 0.1); padding: 0.125rem 0.5rem; border-radius: 0.25rem;">
                            No Estimates
                        </span>
                    @elseif($allBilled)
                        <span style="font-size: 0.6875rem; font-weight: 700; color: #047857; background: rgba(16, 185, 129, 0.12); padding: 0.125rem 0.5rem; border-radius: 0.25rem;">
                            {{ $billedCount }}/{{ $totalQuotesCount }} Billed
                        </span>
                    @else
                        <span style="font-size: 0.6875rem; font-weight: 700; color: #b45309; background: rgba(245, 158, 11, 0.12); padding: 0.125rem 0.5rem; border-radius: 0.25rem;">
                            {{ $billedCount }}/{{ $totalQuotesCount }} Billed
                        </span>
                    @endif
                </div>

                <div style="display: flex; align-items: baseline; gap: 0.5rem;">
                    <span style="font-size: 1.375rem; font-weight: 800; color: #b91c1c; letter-spacing: -0.02em;">
                        ₹{{ number_format($totalVendorCost, 2) }}
                    </span>
                    <span style="font-size: 0.75rem; color: #6b7280;">
                        ({{ $totalQuotesCount }} {{ \Illuminate\Support\Str::plural('Contractor', $totalQuotesCount) }})
                    </span>
                </div>

                <div style="font-size: 0.75rem; color: #4b5563; margin-top: 0.375rem; line-height: 1.4;">
                    @if($awardedOrBilledQuotes->isNotEmpty())
                        <div style="display: flex; flex-direction: column; gap: 0.25rem; max-height: 90px; overflow-y: auto; padding-right: 0.25rem;">
                            @foreach($awardedOrBilledQuotes as $vq)
                                @php
                                    $vName = $vq->vendor?->display_name ?: 'Contractor';
                                    $vTrade = $vq->vendor?->vendorProfile?->trade?->name ?? 'General';
                                    $vCost = number_format((float) $vq->quoted_cost, 2);
                                    $hasBill = ! empty($vq->bill_id);
                                    $billNum = $vq->bill?->bill_number ?? ($hasBill ? "#BILL-{$vq->bill_id}" : null);
                                    $billObj = $vq->bill;
                                @endphp
                                <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.6875rem; background: rgba(128, 128, 128, 0.04); padding: 0.25rem 0.375rem; border-radius: 0.25rem;">
                                    <div style="overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 160px;" title="{{ $vName }} ({{ $vTrade }})">
                                        <strong>{{ $vName }}</strong> <span style="color: #6b7280;">({{ $vTrade }})</span>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 0.375rem; flex-shrink: 0;">
                                        <span style="font-weight: 600;">₹{{ $vCost }}</span>
                                        @if($hasBill && $billObj)
                                            <a href="{{ route('billing.bill.pdf', $billObj) }}"
                                               target="_blank"
                                               style="color: #047857; font-weight: 700; font-family: monospace; text-decoration: underline;"
                                               title="Download Bill PDF">
                                                ✅ {{ $billNum }}
                                            </a>
                                        @elseif($hasBill)
                                            <span style="color: #047857; font-weight: 700; font-family: monospace;">✅ {{ $billNum }}</span>
                                        @else
                                            <span style="color: #d97706; font-size: 0.625rem; font-weight: 600;">Pending</span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div style="color: #6b7280; font-size: 0.6875rem;">No contractor quotes logged yet.</div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Card 3: Financial Net Impact & Margin --}}
        <div style="background: rgba(128, 128, 128, 0.02); border: 1px solid rgba(128, 128, 128, 0.15); border-left: 4px solid {{ $isDwelly ? '#f59e0b' : ($marginAmount >= 0 ? '#10b981' : '#ef4444') }}; border-radius: 0.625rem; padding: 0.875rem 1rem; display: flex; flex-direction: column; justify-content: space-between; gap: 0.625rem;">
            <div>
                <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.375rem;">
                    <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280;">
                        {{ $isDwelly ? 'Operating Expense Impact' : 'Net Margin & Financial Impact' }}
                    </span>
                    @if($isDwelly)
                        <span style="font-size: 0.6875rem; font-weight: 700; color: #92400e; background: rgba(245, 158, 11, 0.12); padding: 0.125rem 0.5rem; border-radius: 0.25rem;">
                            Dwelly Cost
                        </span>
                    @elseif($isDirect)
                        <span style="font-size: 0.6875rem; font-weight: 700; color: #475569; background: rgba(100, 116, 139, 0.12); padding: 0.125rem 0.5rem; border-radius: 0.25rem;">
                            Direct
                        </span>
                    @else
                        <span style="font-size: 0.6875rem; font-weight: 700; color: {{ $marginAmount >= 0 ? '#047857' : '#b91c1c' }}; background: {{ $marginAmount >= 0 ? 'rgba(16, 185, 129, 0.12)' : 'rgba(239, 68, 68, 0.12)' }}; padding: 0.125rem 0.5rem; border-radius: 0.25rem;">
                            {{ $marginAmount >= 0 ? "+{$marginPct}% Margin" : "Loss" }}
                        </span>
                    @endif
                </div>

                <div style="display: flex; align-items: baseline; gap: 0.5rem;">
                    @if($isDwelly)
                        <span style="font-size: 1.375rem; font-weight: 800; color: #d97706; letter-spacing: -0.02em;">
                            -₹{{ number_format($totalVendorCost, 2) }}
                        </span>
                        <span style="font-size: 0.6875rem; color: #92400e; font-weight: 600;">(Dwelly Absorbed)</span>
                    @elseif($isDirect)
                        <span style="font-size: 1.375rem; font-weight: 800; color: #475569; letter-spacing: -0.02em;">
                            ₹0.00
                        </span>
                        <span style="font-size: 0.6875rem; color: #64748b; font-weight: 600;">(Self-Settled)</span>
                    @else
                        <span style="font-size: 1.375rem; font-weight: 800; color: {{ $marginAmount >= 0 ? '#059669' : '#b91c1c' }}; letter-spacing: -0.02em;">
                            {{ $marginAmount >= 0 ? '+' : '' }}₹{{ number_format($marginAmount, 2) }}
                        </span>
                        <span style="font-size: 0.6875rem; color: #6b7280;">({{ $marginPct }}% gross margin)</span>
                    @endif
                </div>

                <div style="font-size: 0.75rem; color: #4b5563; margin-top: 0.375rem; line-height: 1.4;">
                    <div style="display: flex; justify-content: space-between; font-size: 0.6875rem; margin-bottom: 0.125rem;">
                        <span style="color: #6b7280;">Total Client Invoiced:</span>
                        <strong>₹{{ number_format($clientInvoiceTotal, 2) }}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.6875rem;">
                        <span style="color: #6b7280;">Total Vendor Payable:</span>
                        <strong style="color: #b91c1c;">-₹{{ number_format($totalVendorCost, 2) }}</strong>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
