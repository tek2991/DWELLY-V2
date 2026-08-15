<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Maintenance Quotation - {{ $quote->quote_number }} (v{{ $quote->version }})</title>
    <style>
        @page {
            margin: 30px 35px;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
        }
        .logo-title {
            font-size: 24px;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 1px;
        }
        .company-subtitle {
            font-size: 10px;
            color: #64748b;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .quote-title {
            font-size: 18px;
            font-weight: bold;
            color: #2563eb;
            text-align: right;
            text-transform: uppercase;
        }
        .quote-meta {
            font-size: 10px;
            color: #475569;
            text-align: right;
            margin-top: 4px;
        }
        .version-badge {
            display: inline-block;
            background: #2563eb;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 6px;
            border-radius: 4px;
            margin-left: 4px;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
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
            font-size: 11px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-row {
            margin-bottom: 4px;
            font-size: 10px;
        }
        .info-label {
            color: #64748b;
            display: inline-block;
            width: 90px;
            font-weight: bold;
        }
        .info-value {
            color: #0f172a;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .items-table th {
            background: #1e3a8a;
            color: #ffffff;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            padding: 8px 10px;
            text-align: left;
        }
        .items-table th.text-right {
            text-align: right;
        }
        .items-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 10px;
        }
        .items-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .grand-total-box {
            background: #eff6ff;
            border: 2px solid #2563eb;
            border-radius: 6px;
            padding: 10px 14px;
            text-align: right;
            float: right;
            width: 250px;
            margin-bottom: 15px;
        }
        .grand-total-label {
            font-size: 10px;
            font-weight: bold;
            color: #1e3a8a;
            text-transform: uppercase;
        }
        .grand-total-amount {
            font-size: 18px;
            font-weight: bold;
            color: #1d4ed8;
            margin-top: 2px;
        }
        .clear {
            clear: both;
        }
        .terms-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 14px;
            margin-bottom: 20px;
            font-size: 9px;
            color: #475569;
        }
        .terms-title {
            font-size: 10px;
            font-weight: bold;
            color: #1e293b;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .terms-box ol {
            margin: 0;
            padding-left: 14px;
        }
        .terms-box li {
            margin-bottom: 3px;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .signature-cell {
            width: 45%;
            vertical-align: top;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding: 12px;
            background: #ffffff;
        }
        .sign-title {
            font-size: 10px;
            font-weight: bold;
            color: #1e3a8a;
            margin-bottom: 22px;
            text-transform: uppercase;
        }
        .sign-line {
            border-top: 1px solid #94a3b8;
            padding-top: 4px;
            font-size: 9px;
            color: #64748b;
        }
        .footer {
            margin-top: 25px;
            border-top: 1px solid #cbd5e1;
            padding-top: 8px;
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="logo-title">DWELLY</div>
                <div class="company-subtitle">Property Management Solutions</div>
            </td>
            <td style="width: 45%;">
                <div class="quote-title">
                    MAINTENANCE ESTIMATE
                    <span class="version-badge">v{{ $quote->version }}</span>
                </div>
                <div class="quote-meta">
                    <strong>Quotation #:</strong> {{ $quote->quote_number }}<br>
                    <strong>Date:</strong> {{ $quote->generated_at ? $quote->generated_at->format('d M Y') : date('d M Y') }}<br>
                    <strong>Valid Until:</strong> {{ ($quote->generated_at ?? now())->addDays(15)->format('d M Y') }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Context Information -->
    <table class="info-grid">
        <tr>
            <td class="info-box">
                <div class="box-title">Property &amp; Client Details</div>
                <div class="info-row">
                    <span class="info-label">Property:</span>
                    <span class="info-value"><strong>{{ $ticket->property?->building_name ?? 'Property' }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Unit / Space:</span>
                    <span class="info-value">{{ $ticket->property?->unit_number ?? 'Full Property' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Owner:</span>
                    <span class="info-value">{{ $ticket->owner?->display_name ?? 'N/A' }}</span>
                </div>
                @if($ticket->tenant)
                <div class="info-row">
                    <span class="info-label">Tenant:</span>
                    <span class="info-value">{{ $ticket->tenant->display_name }}</span>
                </div>
                @endif
                <div class="info-row">
                    <span class="info-label">Paying Party:</span>
                    <span class="info-value" style="color: #2563eb; font-weight: bold;">
                        {{ $ticket->payer_type?->getLabel() ?? ucfirst((string)$ticket->payer_type) }}
                    </span>
                </div>
            </td>
            <td style="width: 4%;"></td>
            <td class="info-box">
                <div class="box-title">Maintenance Ticket Context</div>
                <div class="info-row">
                    <span class="info-label">Ticket #:</span>
                    <span class="info-value"><strong>#{{ $ticket->ticket_number }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Subject:</span>
                    <span class="info-value">{{ $ticket->title }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Reported By:</span>
                    <span class="info-value">{{ ucfirst((string)$ticket->reported_by) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Severity:</span>
                    <span class="info-value">{{ ucfirst((string)$ticket->severity) }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Defect Scope:</span>
                    <span class="info-value">{{ \Illuminate\Support\Str::limit($ticket->description, 70) }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Itemized Table -->
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 8%;">#</th>
                <th style="width: 52%;">Scope / Item Description</th>
                <th style="width: 10%; text-align: center;">Qty</th>
                <th style="width: 15%;" class="text-right">Unit Price (&#8377;)</th>
                <th style="width: 15%;" class="text-right">Total Price (&#8377;)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($quote->items as $index => $item)
            <tr>
                <td style="color: #64748b; font-weight: bold;">{{ $index + 1 }}</td>
                <td>
                    <strong>{{ $item->description }}</strong>
                </td>
                <td style="text-align: center;">{{ number_format($item->quantity, 0) }}</td>
                <td class="text-right">&#8377; {{ number_format($item->unit_price, 2) }}</td>
                <td class="text-right" style="font-weight: bold;">&#8377; {{ number_format($item->total_price, 2) }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; color: #94a3b8; padding: 15px;">
                    Comprehensive Repair &amp; Maintenance Scope
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Grand Total Box -->
    <div class="grand-total-box">
        <div class="grand-total-label">Grand Total Payable</div>
        <div class="grand-total-amount">&#8377; {{ number_format($quote->total_amount, 2) }}</div>
        <div style="font-size: 9px; color: #64748b; margin-top: 3px;">
            Financial Responsibility: <strong>{{ $ticket->payer_type?->getLabel() ?? ucfirst((string)$ticket->payer_type) }}</strong>
        </div>
    </div>
    <div class="clear"></div>

    <!-- Terms & Conditions -->
    <div class="terms-box">
        <div class="terms-title">Terms &amp; Conditions</div>
        <ol>
            <li><strong>Validity:</strong> This quotation estimate is valid for 15 days from the date of issuance.</li>
            <li><strong>Work Authorization:</strong> Physical on-site repairs will commence only upon receipt of official approval from the authorized paying party.</li>
            <li><strong>Warranty:</strong> All labor and replacement parts supplied under this quotation are covered by Dwelly's standard 30-day quality warranty.</li>
            <li><strong>Scope Changes:</strong> Any unforeseen defect discovered during on-site execution beyond this quotation will be submitted as an addendum before proceeding.</li>
        </ol>
    </div>

    <!-- Authorization Signatures -->
    <table class="signature-table">
        <tr>
            <td class="signature-cell">
                <div class="sign-title">Issued by (Dwelly Operations)</div>
                <div class="sign-line">
                    Authorized Representative<br>
                    <strong>Dwelly Property Management Solutions</strong>
                </div>
            </td>
            <td style="width: 10%;"></td>
            <td class="signature-cell">
                <div class="sign-title">Client Acceptance &amp; Approval</div>
                <div class="sign-line">
                    Authorized Signatory (Owner / Tenant)<br>
                    Date &amp; Approval Confirmation
                </div>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="footer">
        This is an official system-generated document | Dwelly Property Management Solutions | Quotation #{{ $quote->quote_number }} (v{{ $quote->version }})
    </div>

</body>
</html>
