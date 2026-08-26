<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice - {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 30px 35px;
        }
        * {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.45;
        }
        .logo-title {
            font-size: 24px;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 0.5px;
        }
        .company-subtitle {
            font-size: 9.5px;
            color: #64748b;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-title {
            font-size: 20px;
            font-weight: bold;
            color: #2563eb;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-paid { background: #dcfce7; color: #166534; }
        .badge-sent { background: #e0f2fe; color: #0369a1; }
        .badge-draft { background: #f1f5f9; color: #475569; }
        .badge-overdue { background: #fee2e2; color: #991b1b; }
        
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            vertical-align: top;
            width: 48%;
        }
        .box-title {
            font-size: 10.5px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-row {
            margin-bottom: 3px;
            font-size: 10px;
            color: #334155;
        }
        .info-label {
            color: #64748b;
            display: inline-block;
            width: 75px;
            font-weight: bold;
        }
        .info-value {
            color: #0f172a;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .items-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            text-align: left;
            padding: 8px 10px;
            border-bottom: 2px solid #cbd5e1;
            border-top: 1px solid #cbd5e1;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            font-size: 10.5px;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }

        .summary-wrapper {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .summary-table {
            width: 270px;
            float: right;
            border-collapse: collapse;
        }
        .summary-table td {
            padding: 4px 8px;
            font-size: 10.5px;
        }
        .summary-label {
            color: #64748b;
            text-align: right;
        }
        .summary-val {
            text-align: right;
            font-weight: bold;
            color: #0f172a;
        }
        .grand-total-row td {
            font-weight: bold;
            font-size: 13px;
            border-top: 2px solid #cbd5e1;
            border-bottom: 2px solid #cbd5e1;
            color: #1e3a8a;
            background-color: #eff6ff;
            padding: 6px 8px;
        }
        .balance-due-row td {
            font-weight: bold;
            font-size: 12px;
            color: #b91c1c;
            padding: 6px 8px;
        }

        .notes-section {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            clear: both;
        }
        .notes-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-left: 4px solid #2563eb;
            border-radius: 4px;
            padding: 10px 12px;
            font-size: 10px;
            color: #334155;
        }
        .notes-title {
            font-weight: bold;
            margin-bottom: 3px;
            color: #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .signatory-box {
            text-align: right;
            font-size: 10.5px;
            color: #334155;
            padding-top: 20px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 25px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 9.5px;
            color: #64748b;
        }
    </style>
</head>
<body>
    @php
        $statusStr = $invoice->status instanceof \BackedEnum ? $invoice->status->value : (string) ($invoice->status ?? 'sent');
        $statusClass = match(strtolower($statusStr)) {
            'paid' => 'badge-paid',
            'sent' => 'badge-sent',
            'overdue' => 'badge-overdue',
            default => 'badge-draft'
        };

        $branch = $invoice->branch;
        $organization = $branch ? $branch->organization : \Tek2991\Accounting\Models\Organization::current();
        $companyName = $organization->name ?? 'Dwelly Property Management';
        $branchName = $branch ? $branch->name : null;
        $branchAddress = $branch ? implode(', ', array_filter([$branch->address, $branch->city, $branch->state?->name, $branch->postal_code])) : '';
        $branchPhone = $branch->phone ?? ($organization->phone ?? '');
        $branchEmail = $branch->email ?? ($organization->email ?? '');
        $branchGstin = $branch?->gstRegistration?->gstin ?? ($organization->gstin ?? '');
    @endphp

    {{-- Header --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="vertical-align: top; width: 50%;">
                <div class="logo-title">{{ $companyName }}</div>
                <div class="company-subtitle">Property Management &bull; Tax Invoice</div>
                @if($branchName)
                    <div style="font-size: 10px; color: #475569; margin-top: 4px; font-weight: bold;">Branch: {{ $branchName }}</div>
                @endif
                @if($branchAddress)
                    <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">{{ $branchAddress }}</div>
                @endif
                @if($branchGstin)
                    <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">GSTIN: {{ $branchGstin }}</div>
                @endif
                @if($organization && $organization->pan)
                    <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">PAN: {{ $organization->pan }}</div>
                @endif
            </td>
            <td style="vertical-align: top; width: 50%; text-align: right;">
                <div class="doc-title" style="margin-bottom: 6px;">Tax Invoice</div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Invoice Number:</td>
                        <td style="text-align: right; font-weight: bold; font-size: 10.5px; padding: 2px 0 2px 8px; width: 140px;">{{ $invoice->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Issue Date:</td>
                        <td style="text-align: right; font-weight: bold; font-size: 10.5px; padding: 2px 0 2px 8px;">{{ $invoice->issue_date ? $invoice->issue_date->format('d M Y') : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Due Date:</td>
                        <td style="text-align: right; font-weight: bold; font-size: 10.5px; padding: 2px 0 2px 8px;">{{ $invoice->due_date ? $invoice->due_date->format('d M Y') : 'N/A' }}</td>
                    </tr>
                    @if($invoice->billing_period_formatted)
                    <tr>
                        <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Billing Period:</td>
                        <td style="text-align: right; font-weight: bold; font-size: 10.5px; padding: 2px 0 2px 8px; color: #1e3a8a;">{{ $invoice->billing_period_formatted }}</td>
                    </tr>
                    @endif
                    <tr>
                        <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Status:</td>
                        <td style="text-align: right; padding: 2px 0 2px 8px;">
                            <span class="badge {{ $statusClass }}">{{ strtoupper(str_replace('_', ' ', $statusStr)) }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Clean Divider Line --}}
    <div style="width: 100%; height: 2px; background: #2563eb; margin-bottom: 18px;"></div>

    {{-- Party info grid --}}
    <table class="info-grid">
        <tr>
            <td class="info-box">
                <div class="box-title">Bill To (Client / Customer)</div>
                <div style="font-weight: bold; font-size: 11px; color: #0f172a; margin-bottom: 4px;">
                    {{ $invoice->contact?->name ?: 'Client' }}
                </div>
                @if($invoice->contact?->billing_address)
                    <div class="info-row">
                        <span class="info-label">Address:</span>
                        <span class="info-value">
                            @if(is_array($invoice->contact->billing_address))
                                {{ implode(', ', $invoice->contact->billing_address) }}
                            @else
                                {{ $invoice->contact->billing_address }}
                            @endif
                        </span>
                    </div>
                @endif
                @if($invoice->contact?->phone)
                    <div class="info-row">
                        <span class="info-label">Phone:</span>
                        <span class="info-value">{{ $invoice->contact->phone }}</span>
                    </div>
                @endif
                @if($invoice->contact?->email)
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value">{{ $invoice->contact->email }}</span>
                    </div>
                @endif
                @if($invoice->contact?->gstin)
                    <div class="info-row">
                        <span class="info-label">GSTIN:</span>
                        <span class="info-value" style="font-weight: bold;">{{ $invoice->contact->gstin }}</span>
                    </div>
                @endif
            </td>
            <td style="width: 4%;"></td>
            <td class="info-box">
                <div class="box-title">Service Location / Issuer</div>
                <div style="font-weight: bold; font-size: 11px; color: #0f172a; margin-bottom: 4px;">
                    {{ $companyName }}
                </div>
                @if($branchName)
                    <div class="info-row">
                        <span class="info-label">Branch:</span>
                        <span class="info-value">{{ $branchName }}</span>
                    </div>
                @endif
                @if($branchAddress)
                    <div class="info-row">
                        <span class="info-label">Address:</span>
                        <span class="info-value">{{ $branchAddress }}</span>
                    </div>
                @endif
                @if($branchEmail || $branchPhone)
                    <div class="info-row">
                        <span class="info-label">Contact:</span>
                        <span class="info-value">{{ $branchPhone }} {{ $branchEmail ? "({$branchEmail})" : '' }}</span>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Line items table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 45%;">Description / Service Scope</th>
                <th class="text-right" style="width: 15%;">Unit Rate</th>
                <th class="text-center" style="width: 10%;">Qty</th>
                <th class="text-right" style="width: 15%;">Tax</th>
                <th class="text-right" style="width: 15%;">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($invoice->items as $item)
                @php
                    $unitPrice = (float) $item->unit_price;
                    $qty = (float) ($item->quantity ?: 1);
                    $net = (float) ($item->gross_amount ?: $unitPrice * $qty);
                    $taxAmt = (float) ($item->tax_amount ?? 0);
                    $total = (float) ($item->total_price ?: ($item->line_total ?: $net + $taxAmt));
                    $itemDesc = str_replace(["\xc2\xa0", "\xe2\x80\x91", "\xe2\x80\x93", "\xe2\x80\x94"], '-', $item->description ?: ($item->item?->name ?? 'Maintenance Service'));
                @endphp
                <tr>
                    <td>
                        <strong>{{ $itemDesc }}</strong>
                        @if($item->hsn_sac_code)
                            <span style="color: #64748b; font-size: 9.5px;"> (HSN/SAC: {{ $item->hsn_sac_code }})</span>
                        @endif
                    </td>
                    <td class="text-right">&#8377; {{ number_format($unitPrice, 2) }}</td>
                    <td class="text-center">{{ number_format($qty, 2) }}</td>
                    <td class="text-right">
                        @if($taxAmt > 0)
                            &#8377; {{ number_format($taxAmt, 2) }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right" style="font-weight: bold;">&#8377; {{ number_format($total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td><strong>{{ str_replace(["\xc2\xa0", "\xe2\x80\x91", "\xe2\x80\x93", "\xe2\x80\x94"], '-', $invoice->notes ?: 'Maintenance Services') }}</strong></td>
                    <td class="text-right">&#8377; {{ number_format((float) $invoice->grand_total, 2) }}</td>
                    <td class="text-center">1.00</td>
                    <td class="text-right">-</td>
                    <td class="text-right" style="font-weight: bold;">&#8377; {{ number_format((float) $invoice->grand_total, 2) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Summary table --}}
    <div class="summary-wrapper">
        <table class="summary-table" align="right">
            <tr>
                <td class="summary-label">Subtotal:</td>
                <td class="summary-val">&#8377; {{ number_format((float) ($invoice->subtotal ?: $invoice->grand_total), 2) }}</td>
            </tr>
            @if((float) $invoice->tax_total > 0)
                <tr>
                    <td class="summary-label">Tax Total:</td>
                    <td class="summary-val">&#8377; {{ number_format((float) $invoice->tax_total, 2) }}</td>
                </tr>
            @endif
            @if((float) $invoice->discount_amount > 0)
                <tr>
                    <td class="summary-label">Discount:</td>
                    <td class="summary-val">-&#8377; {{ number_format((float) $invoice->discount_amount, 2) }}</td>
                </tr>
            @endif
            <tr class="grand-total-row">
                <td style="text-align: right;">Grand Total:</td>
                <td style="text-align: right;">&#8377; {{ number_format((float) $invoice->grand_total, 2) }}</td>
            </tr>
            @if((float) $invoice->amount_paid > 0)
                <tr>
                    <td class="summary-label">Amount Paid:</td>
                    <td class="summary-val">&#8377; {{ number_format((float) $invoice->amount_paid, 2) }}</td>
                </tr>
            @endif
            <tr class="balance-due-row">
                <td style="text-align: right;">Balance Due:</td>
                <td style="text-align: right;">&#8377; {{ number_format((float) ($invoice->balance_due ?? $invoice->grand_total), 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Notes & Signatory Section --}}
    <table class="notes-section">
        <tr>
            <td style="width: 60%; vertical-align: top; padding-right: 20px;">
                @if($invoice->notes)
                    <div class="notes-box">
                        <div class="notes-title">Notes &amp; Remarks:</div>
                        <div>{{ str_replace(["\xc2\xa0", "\xe2\x80\x91", "\xe2\x80\x93", "\xe2\x80\x94"], '-', $invoice->notes) }}</div>
                    </div>
                @endif
            </td>
            <td style="width: 40%; vertical-align: bottom; text-align: right;">
                <div class="signatory-box">
                    <div style="font-weight: bold; margin-bottom: 40px;">For {{ $companyName }}</div>
                    <div style="border-top: 1px solid #cbd5e1; display: inline-block; width: 160px; padding-top: 4px;">
                        Authorized Signatory
                    </div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Footer --}}
    <table class="footer-table">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                Thank you for your business. For billing queries, please contact {{ $branchEmail ?: ($organization->email ?? 'support@dwelly.in') }}.
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                Generated: {{ now()->format('d M Y, h:i A') }}
            </td>
        </tr>
    </table>
</body>
</html>
