<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Work Order - {{ $vendorQuote->work_order_number }}</title>
    <style>
        @page {
            margin: 22px 32px 18px 32px;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.35;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
            border-bottom: 2px solid #2563eb;
            padding-bottom: 6px;
        }
        .logo-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e3a8a;
            letter-spacing: 0.5px;
        }
        .company-subtitle {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 1px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .doc-title {
            font-size: 15px;
            font-weight: bold;
            color: #2563eb;
            text-align: right;
            text-transform: uppercase;
        }
        .doc-meta {
            font-size: 9px;
            color: #475569;
            text-align: right;
            margin-top: 2px;
        }
        .badge-issued {
            display: inline-block;
            background: #16a34a;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            padding: 1px 5px;
            border-radius: 3px;
            margin-left: 3px;
            vertical-align: middle;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .info-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            padding: 6px 9px;
            vertical-align: top;
            width: 48%;
        }
        .box-title {
            font-size: 9.5px;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 2px;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .info-row {
            margin-bottom: 2px;
            font-size: 9px;
            line-height: 1.25;
        }
        .info-label {
            color: #64748b;
            display: inline-block;
            width: 95px;
            font-weight: bold;
        }
        .info-value {
            color: #0f172a;
        }
        .subject-box {
            background: #eff6ff;
            border-left: 3px solid #2563eb;
            padding: 5px 9px;
            margin-bottom: 9px;
            font-size: 9.5px;
            border-radius: 0 4px 4px 0;
            line-height: 1.3;
        }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            color: #1e3a8a;
            margin-top: 7px;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 2px;
        }
        .scope-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 5px;
            padding: 6px 9px;
            margin-bottom: 8px;
            font-size: 9.5px;
            page-break-inside: avoid;
        }
        .trade-heading {
            font-weight: bold;
            font-size: 10.5px;
            color: #1e3a8a;
            margin-bottom: 3px;
        }
        .commercial-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }
        .commercial-table th {
            background: #f1f5f9;
            color: #334155;
            padding: 4px 7px;
            font-size: 8.5px;
            text-transform: uppercase;
            text-align: left;
            border: 1px solid #cbd5e1;
        }
        .commercial-table td {
            padding: 4px 7px;
            font-size: 9px;
            border: 1px solid #cbd5e1;
        }
        .terms-container {
            margin-bottom: 6px;
            page-break-inside: avoid;
        }
        .terms-list {
            margin: 0;
            padding-left: 14px;
            font-size: 8.5px;
            color: #334155;
            line-height: 1.3;
        }
        .terms-list li {
            margin-bottom: 1.5px;
        }
        .signature-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            page-break-inside: avoid;
        }
        .signature-cell {
            width: 50%;
            vertical-align: top;
        }
        .signature-line {
            border-top: 1px solid #94a3b8;
            width: 80%;
            margin-top: 22px;
            padding-top: 2px;
            font-size: 8.5px;
            color: #475569;
        }
        .footer {
            margin-top: 8px;
            border-top: 1px solid #e2e8f0;
            padding-top: 4px;
            text-align: center;
            font-size: 8px;
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
                <div class="company-subtitle">Property Management &amp; Maintenance Operations</div>
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
                <div class="box-title">Job Site &amp; Ticket Reference</div>
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
                    @php
                        $addressParts = array_filter([
                            $ticket?->property?->address_line1,
                            $ticket?->property?->localityRef?->name,
                            $ticket?->property?->city,
                        ]);
                    @endphp
                    <span class="info-value">{{ !empty($addressParts) ? implode(', ', $addressParts) : 'N/A' }}</span>
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
        <strong>SUBJECT:</strong> Authorization &amp; Official Work Order to Commence Repairs as per Vendor Quotation 
        <strong>#{{ $vendorQuote->vendor_quote_number ?: 'N/A' }}</strong> 
        dated <strong>{{ $vendorQuote->vendor_quote_date ? $vendorQuote->vendor_quote_date->format('d M Y') : 'N/A' }}</strong>.
    </div>

    @php
        $hasLinkedItems = isset($linkedItems) && $linkedItems->isNotEmpty();
        $agreedCost = (float) ($vendorQuote->final_cost ?? ($hasLinkedItems ? $linkedItems->sum(fn ($i) => (float) ($i->quantity ?? 1) * (float) ($i->vendor_cost ?? 0)) : $vendorQuote->quoted_cost));
        $hasCostAdjustment = abs($agreedCost - (float) $vendorQuote->quoted_cost) >= 0.01;
    @endphp

    <!-- Scope of Work -->
    <div class="section-title">Authorized Scope of Work &amp; Specifications</div>
    <div class="scope-card">
        <div class="trade-heading">
            Trade: {{ $vendorQuote->trade_title }}
        </div>
        @if($hasLinkedItems)
            <div>
                <span style="font-size: 9px; font-weight: bold; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px;">Approved Itemized Scope of Work:</span>
                <table style="width: 100%; border-collapse: collapse; margin-top: 3px;">
                    @foreach($linkedItems as $idx => $item)
                        <tr>
                            <td style="padding: 2px 0; vertical-align: top; width: 18px; font-weight: bold; color: #2563eb;">{{ $idx + 1 }}.</td>
                            <td style="padding: 2px 0; vertical-align: top;">
                                <strong>{{ $item->description }}</strong>
                                @if($item->defectItem)
                                    <span style="font-size: 8.5px; color: #64748b; margin-left: 4px;">
                                        (Defect: {{ $item->defectItem->issue_description }}@if($item->defectItem->repair_action) &bull; Action: {{ $item->defectItem->repair_action }}@endif)
                                    </span>
                                @endif
                            </td>
                            <td style="padding: 2px 0; vertical-align: top; text-align: right; width: 60px; color: #475569; font-size: 8.5px;">
                                Qty: {{ (float) $item->quantity }}
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
            @if($vendorQuote->scope_of_work)
                <div style="color: #475569; font-size: 8.5px; border-top: 1px dashed #cbd5e1; padding-top: 3px; margin-top: 3px; line-height: 1.3;">
                    <strong style="color: #334155;">Contractor Technical Notes:</strong> {{ $vendorQuote->scope_of_work }}
                </div>
            @endif
        @else
            <div style="color: #334155; line-height: 1.35;">
                {!! nl2br(e($vendorQuote->scope_of_work ?: 'Work to be executed in accordance with technical assessment and trade standards.')) !!}
            </div>
        @endif
    </div>

    <!-- Commercial Consideration -->
    <div class="section-title">Commercial Terms &amp; Agreed Price</div>
    <table class="commercial-table">
        <thead>
            <tr>
                <th style="width: 48%;">Scope / Item Description</th>
                <th style="width: 27%;">Vendor Quotation Reference</th>
                <th style="width: 25%; text-align: right;">Agreed Cost (INR)</th>
            </tr>
        </thead>
        <tbody>
            @if($hasLinkedItems)
                @foreach($linkedItems as $item)
                    @php
                        $itemCost = round((float) ($item->quantity ?? 1) * (float) ($item->vendor_cost ?? 0), 2);
                    @endphp
                    <tr>
                        <td>
                            <strong>{{ $item->description }}</strong>
                            <span style="font-size: 8.5px; color: #64748b; margin-left: 4px;">
                                (Qty: {{ (float) $item->quantity }} &times; ₹{{ number_format((float) $item->vendor_cost, 2) }})
                            </span>
                        </td>
                        <td>
                            Quote #: <strong>{{ $vendorQuote->vendor_quote_number ?: 'N/A' }}</strong>
                            @if($vendorQuote->vendor_quote_date)
                                <span style="font-size: 8.5px; color: #64748b;">({{ $vendorQuote->vendor_quote_date->format('d M Y') }})</span>
                            @endif
                        </td>
                        <td style="text-align: right; font-weight: bold; color: #334155;">
                            ₹{{ number_format($itemCost, 2) }}
                        </td>
                    </tr>
                @endforeach
                <tr style="background-color: #f8fafc;">
                    <td colspan="2" style="text-align: right; font-weight: bold; font-size: 9px; text-transform: uppercase;">
                        Total Authorized Contract Price:
                    </td>
                    <td style="text-align: right; font-size: 10.5px; font-weight: bold; color: #16a34a;">
                        ₹{{ number_format($agreedCost, 2) }}
                    </td>
                </tr>
            @else
                <tr>
                    <td>
                        <strong>{{ $vendorQuote->trade_title }}</strong>
                        <div style="font-size: 8.5px; color: #64748b;">Includes materials, labor, transport, and site execution</div>
                    </td>
                    <td>
                        Quote #: <strong>{{ $vendorQuote->vendor_quote_number ?: 'N/A' }}</strong>
                        @if($vendorQuote->vendor_quote_date)
                            <span style="font-size: 8.5px; color: #64748b;">({{ $vendorQuote->vendor_quote_date->format('d M Y') }})</span>
                        @endif
                    </td>
                    <td style="text-align: right; font-size: 10.5px; font-weight: bold; color: #16a34a;">
                        ₹{{ number_format($agreedCost, 2) }}
                    </td>
                </tr>
            @endif
        </tbody>
    </table>
    @if($hasCostAdjustment)
        <div style="font-size: 8.5px; color: #64748b; margin-top: -4px; margin-bottom: 6px; font-style: italic;">
            * Note: Authorized contract price reflects agreed quotation scope (Original contractor bid: ₹{{ number_format((float) $vendorQuote->quoted_cost, 2) }}).
        </div>
    @endif

    <!-- Instructions and Terms -->
    <div class="section-title">Execution Instructions &amp; Terms of Commencement</div>
    <div class="terms-container">
        <ul class="terms-list">
            <li><strong>Site Access &amp; Mobilization:</strong> You are authorized to contact property occupants/operations desk and commence physical execution immediately.</li>
            <li><strong>Quality Standards:</strong> All materials installed must match or exceed the specifications stated in your approved estimate sheet.</li>
            <li><strong>Site Safety &amp; Housekeeping:</strong> The contractor shall ensure technician safety on-site, protect existing property fixtures, and remove all debris upon completion.</li>
            <li><strong>Completion Verification:</strong> Promptly inform the Dwelly operations manager upon completion of physical works to facilitate the verification audit.</li>
            <li><strong>Billing &amp; Settlement:</strong> Submit your final tax invoice referencing Work Order #{{ $vendorQuote->work_order_number }} following successful inspection sign-off.</li>
        </ul>
    </div>

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
        This document is an electronically generated official Work Order issued by Dwelly Operations &bull; Work Order Ref: {{ $vendorQuote->work_order_number }}
    </div>

</body>
</html>
