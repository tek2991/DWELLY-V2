<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Work Order - {{ $vendorQuote->work_order_number }}</title>
    <style>
        @page {
            margin: 35px 40px;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.5;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
        }
        .logo-title {
            font-size: 22px;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 1px;
        }
        .company-subtitle {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-title {
            font-size: 16px;
            font-weight: bold;
            color: #2563eb;
            text-align: right;
            text-transform: uppercase;
        }
        .doc-meta {
            font-size: 10px;
            color: #475569;
            text-align: right;
            margin-top: 4px;
        }
        .badge-issued {
            display: inline-block;
            background: #16a34a;
            color: #ffffff;
            font-size: 9px;
            font-weight: bold;
            padding: 2px 7px;
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
            padding: 12px;
            vertical-align: top;
            width: 48%;
        }
        .box-title {
            font-size: 11px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-row {
            margin-bottom: 5px;
            font-size: 10px;
        }
        .info-label {
            color: #64748b;
            display: inline-block;
            width: 100px;
            font-weight: bold;
        }
        .info-value {
            color: #0f172a;
        }
        .subject-box {
            background: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 10px 14px;
            margin-bottom: 18px;
            font-size: 11px;
            border-radius: 0 4px 4px 0;
        }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 14px;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 3px;
        }
        .scope-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 12px 14px;
            margin-bottom: 18px;
            font-size: 11px;
        }
        .commercial-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
        }
        .commercial-table th {
            background: #f1f5f9;
            color: #334155;
            padding: 8px 10px;
            font-size: 10px;
            text-transform: uppercase;
            text-align: left;
            border: 1px solid #cbd5e1;
        }
        .commercial-table td {
            padding: 8px 10px;
            font-size: 11px;
            border: 1px solid #cbd5e1;
        }
        .terms-list {
            margin: 0;
            padding-left: 18px;
            font-size: 10px;
            color: #334155;
            line-height: 1.6;
        }
        .terms-list li {
            margin-bottom: 4px;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
        }
        .signature-cell {
            width: 50%;
            vertical-align: top;
            padding-top: 10px;
        }
        .signature-line {
            border-top: 1px solid #94a3b8;
            width: 80%;
            margin-top: 40px;
            padding-top: 4px;
            font-size: 10px;
            color: #475569;
        }
        .footer {
            margin-top: 25px;
            border-top: 1px solid #e2e8f0;
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
            <td style="width: 50%; vertical-align: middle;">
                <div class="logo-title">DWELLY</div>
                <div class="company-subtitle">Property Management & Maintenance Operations</div>
            </td>
            <td style="width: 50%; vertical-align: middle; text-align: right;">
                <div class="doc-title">WORK ORDER</div>
                <div class="doc-meta">
                    <strong>Ref #:</strong> {{ $vendorQuote->work_order_number }}
                    <span class="badge-issued">ISSUED</span><br>
                    <strong>Date of Issuance:</strong> {{ $vendorQuote->work_order_issued_at ? $vendorQuote->work_order_issued_at->format('d M Y') : date('d M Y') }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Parties & Site Details -->
    <table class="info-grid">
        <tr>
            <td class="info-box">
                <div class="box-title">Awarded Contractor / Vendor</div>
                <div class="info-row">
                    <span class="info-label">Vendor Name:</span>
                    <span class="info-value"><strong>{{ $vendorQuote->vendor?->display_name ?? 'Contractor' }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Trade / Category:</span>
                    <span class="info-value">{{ $vendorQuote->vendorTrade?->name ?? ($vendorQuote->vendor?->vendorProfile?->trade?->name ?? 'General Maintenance') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone:</span>
                    <span class="info-value">{{ $vendorQuote->vendor?->phone ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email:</span>
                    <span class="info-value">{{ $vendorQuote->vendor?->email ?? 'N/A' }}</span>
                </div>
                @if($vendorQuote->vendor?->vendorProfile?->company_gstin)
                <div class="info-row">
                    <span class="info-label">GSTIN:</span>
                    <span class="info-value">{{ $vendorQuote->vendor->vendorProfile->company_gstin }}</span>
                </div>
                @endif
            </td>
            <td style="width: 4%;"></td>
            <td class="info-box">
                <div class="box-title">Job Site & Ticket Reference</div>
                <div class="info-row">
                    <span class="info-label">Operations Ticket:</span>
                    <span class="info-value"><strong>#{{ $ticket?->ticket_number }}</strong> - {{ $ticket?->title }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Property / Unit:</span>
                    <span class="info-value"><strong>{{ $ticket?->property?->building_name ?? 'Property' }}</strong></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Property Address:</span>
                    <span class="info-value">
                        {{ $ticket?->property?->address_line1 ?: '' }}
                        {{ $ticket?->property?->localityRef?->name ? ', ' . $ticket->property->localityRef->name : '' }}
                        {{ $ticket?->property?->city ? ', ' . $ticket->property->city : '' }}
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">Client Quote Ref:</span>
                    <span class="info-value">{{ $clientQuote?->quote_number ?? 'Approved Quotation' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Subject Banner -->
    <div class="subject-box">
        <strong>SUBJECT:</strong> Authorization & Official Work Order to Commence Repairs as per Vendor Quotation 
        <strong>#{{ $vendorQuote->vendor_quote_number ?: 'N/A' }}</strong> 
        dated <strong>{{ $vendorQuote->vendor_quote_date ? $vendorQuote->vendor_quote_date->format('d M Y') : 'N/A' }}</strong>.
    </div>

    <!-- Scope of Work -->
    <div class="section-title">Authorized Scope of Work & Specifications</div>
    <div class="scope-card">
        <div style="font-weight: bold; font-size: 12px; color: #1e3a8a; margin-bottom: 6px;">
            🛠 {{ $vendorQuote->trade_title }}
        </div>
        <div style="color: #334155; line-height: 1.6;">
            {!! nl2br(e($vendorQuote->scope_of_work ?: 'Work to be executed in accordance with technical assessment and trade standards.')) !!}
        </div>
    </div>

    <!-- Commercial Consideration -->
    <div class="section-title">Commercial Terms & Agreed Price</div>
    <table class="commercial-table">
        <thead>
            <tr>
                <th style="width: 45%;">Description</th>
                <th style="width: 25%;">Vendor Quotation Reference</th>
                <th style="width: 30%; text-align: right;">Agreed Contract Cost (INR)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>{{ $vendorQuote->trade_title }}</strong><br>
                    <span style="font-size: 10px; color: #64748b;">Includes all materials, labor, transport, and site execution</span>
                </td>
                <td>
                    Quote #: <strong>{{ $vendorQuote->vendor_quote_number ?: 'N/A' }}</strong><br>
                    Dated: {{ $vendorQuote->vendor_quote_date ? $vendorQuote->vendor_quote_date->format('d M Y') : 'N/A' }}
                </td>
                <td style="text-align: right; font-size: 13px; font-weight: bold; color: #16a34a;">
                    ₹{{ number_format((float)$vendorQuote->quoted_cost, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Instructions and Terms -->
    <div class="section-title">Execution Instructions & Terms of Commencement</div>
    <ul class="terms-list">
        <li><strong>Site Access & Mobilization:</strong> You are authorized to contact the property occupants/operations desk and commence physical execution immediately.</li>
        <li><strong>Quality Standards:</strong> All materials installed must match or exceed the specifications stated in your approved estimate sheet.</li>
        <li><strong>Site Safety & Housekeeping:</strong> The contractor shall ensure technician safety on-site, protect existing property fixtures, and remove all construction debris upon completion.</li>
        <li><strong>Completion Verification:</strong> Promptly inform the Dwelly operations manager upon completion of physical works to facilitate the verification audit.</li>
        <li><strong>Billing & Settlement:</strong> Submit your final tax invoice referencing Work Order #{{ $vendorQuote->work_order_number }} following successful inspection sign-off.</li>
    </ul>

    <!-- Signatures -->
    <table class="signature-table">
        <tr>
            <td class="signature-cell">
                <div class="signature-line">
                    <strong>Authorized Signatory</strong><br>
                    Dwelly Property Management Operations Division
                </div>
            </td>
            <td class="signature-cell" style="text-align: right;">
                <div class="signature-line" style="margin-left: auto;">
                    <strong>Contractor Acknowledgment</strong><br>
                    {{ $vendorQuote->vendor?->display_name ?? 'Service Provider' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="footer">
        This document is an electronically generated official Work Order issued by Dwelly Operations &bull; Work Order Ref: {{ $vendorQuote->work_order_number }} &bull; Page 1 of 1
    </div>

</body>
</html>
