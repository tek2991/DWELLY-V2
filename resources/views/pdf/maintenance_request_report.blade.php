<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Maintenance Dossier - {{ $ticket->ticket_number }}</title>
    <style>
        @page {
            margin: 24px 30px 36px 30px;
        }
        * {
            font-family: 'DejaVu Sans', sans-serif;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9.5px;
            color: #0f172a;
            margin: 0;
            padding: 0;
            line-height: 1.35;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-bottom: 2.5px solid #1e3a8a;
            padding-bottom: 8px;
        }
        .logo-title {
            font-size: 22px;
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
        .dossier-title {
            font-size: 14px;
            font-weight: bold;
            color: #1e3a8a;
            text-align: right;
            text-transform: uppercase;
        }
        .dossier-meta {
            font-size: 8.5px;
            color: #475569;
            text-align: right;
            margin-top: 3px;
        }
        .status-badge {
            display: inline-block;
            background: #1e3a8a;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: bold;
            padding: 2px 7px;
            border-radius: 3px;
            text-transform: uppercase;
        }
        .section-header {
            background-color: #f1f5f9;
            color: #1e3a8a;
            font-size: 10px;
            font-weight: bold;
            padding: 5px 8px;
            margin-top: 14px;
            margin-bottom: 8px;
            border-left: 3.5px solid #1e3a8a;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
        }
        .info-grid td {
            vertical-align: top;
            padding: 3px 6px;
            font-size: 9px;
        }
        .info-label {
            color: #64748b;
            font-weight: bold;
            width: 25%;
        }
        .info-val {
            color: #0f172a;
            width: 25%;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 8px;
        }
        .data-table th {
            background-color: #1e3a8a;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: bold;
            text-align: left;
            padding: 5px 8px;
            text-transform: uppercase;
        }
        .data-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 8.5px;
            vertical-align: middle;
        }
        .data-table tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .item-card {
            border: 1.5px solid #cbd5e1;
            border-radius: 4px;
            margin-bottom: 14px;
            background: #ffffff;
            page-break-inside: avoid;
            overflow: hidden;
        }
        .item-card-header {
            background: #f8fafc;
            padding: 6px 10px;
            border-bottom: 1.5px solid #cbd5e1;
        }
        .item-details-table {
            width: 100%;
            border-collapse: collapse;
            background: #ffffff;
        }
        .item-details-table td {
            vertical-align: top;
            padding: 6px 10px;
            font-size: 9px;
        }
        .evidence-comparison-table {
            width: 100%;
            border-collapse: collapse;
        }
        .evidence-comparison-table td {
            vertical-align: top;
            padding: 6px;
            width: 50%;
        }
        .evidence-pane-before {
            border: 1.5px solid #94a3b8;
            border-radius: 4px;
            background: #f8fafc;
            overflow: hidden;
        }
        .evidence-pane-after {
            border: 1.5px solid #059669;
            border-radius: 4px;
            background: #f0fdf4;
            overflow: hidden;
        }
        .pane-header-before {
            background: #e2e8f0;
            color: #1e293b;
            font-size: 8.5px;
            font-weight: bold;
            padding: 4px 8px;
            border-bottom: 1px solid #cbd5e1;
            text-transform: uppercase;
        }
        .pane-header-after {
            background: #dcfce7;
            color: #166534;
            font-size: 8.5px;
            font-weight: bold;
            padding: 4px 8px;
            border-bottom: 1px solid #86efac;
            text-transform: uppercase;
        }
        .pane-body {
            padding: 6px;
            text-align: center;
        }
        .pane-photo {
            margin-bottom: 6px;
            border: 1px solid #cbd5e1;
            border-radius: 3px;
            padding: 4px;
            background: #ffffff;
        }
        .pane-photo img {
            width: 100%;
            max-height: 190px;
            object-fit: contain;
            display: block;
            margin: 0 auto;
            border-radius: 2px;
        }
        .pane-photo-label {
            font-size: 8px;
            color: #64748b;
            margin-top: 3px;
            font-weight: bold;
        }
        .no-photo-box {
            padding: 24px 10px;
            font-size: 8.5px;
            color: #94a3b8;
            font-style: italic;
            text-align: center;
        }
        .financial-container {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }
        .financial-container td {
            vertical-align: top;
        }
        .total-row {
            font-weight: bold;
            font-size: 9.5px;
            background: #e2e8f0;
            border-top: 1.5px solid #94a3b8;
        }
        .signoff-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8px;
            page-break-inside: avoid;
        }
        .signoff-box {
            border: 1.5px solid #cbd5e1;
            border-radius: 4px;
            padding: 8px 10px;
            background: #f8fafc;
            vertical-align: top;
            width: 48%;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>

    <!-- Dynamic Page Numbering Script for DomPDF -->
    <script type="text/php">
        if (isset($pdf)) {
            $text = "Dwelly Property Management • Ticket #{{ $ticket->ticket_number }} • Page " . $PAGE_NUM . " of " . $PAGE_COUNT;
            $size = 7.5;
            $font = $fontMetrics->getFont("DejaVu Sans", "normal");
            $width = $fontMetrics->get_text_width($text, $font, $size);
            $x = ($pdf->get_width() - $width) / 2;
            $y = $pdf->get_height() - 22;
            $pdf->page_text($x, $y, $text, $font, $size, [0.4, 0.4, 0.4]);
        }
    </script>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td style="width: 55%;">
                <div class="logo-title">DWELLY</div>
                <div class="company-subtitle">Property Management & Maintenance Services</div>
                <div style="font-size: 8.5px; color: #475569; margin-top: 2px;">
                    Maintenance Request & Execution Dossier
                </div>
            </td>
            <td style="width: 45%;">
                <div class="dossier-title">Ticket #{{ $ticket->ticket_number }}</div>
                <div class="dossier-meta">
                    <strong>Generated:</strong> {{ $generatedAt }}<br>
                    <strong>Status:</strong> 
                    <span class="status-badge">{{ $ticket->status?->getLabel() ?? ucfirst($ticket->status) }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- 1. Overview Information -->
    <div class="section-header">1. Maintenance Ticket Overview</div>
    <table class="info-grid">
        <tr>
            <td class="info-label">Issue Title:</td>
            <td class="info-val" colspan="3"><strong>{{ $ticket->title }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">Property:</td>
            <td class="info-val">
                <strong>
                    @if($property?->code)
                        [{{ $property->code }}]
                    @endif
                    {{ $property?->building_name ?: ($property?->name ?: 'N/A') }}
                </strong>
            </td>
            <td class="info-label">Reported Date:</td>
            <td class="info-val">{{ $ticket->created_at?->format('d M Y, h:i A') ?? 'N/A' }}</td>
        </tr>
        <tr>
            <td class="info-label">Property Address:</td>
            <td class="info-val">{{ implode(', ', array_filter([$property?->address_line_1, $property?->address_line_2, $property?->locality, $property?->city, $property?->pincode])) ?: ($property?->street_address ?: ($property?->address ?: ($property?->localityRef?->name ?: 'N/A'))) }}</td>
            <td class="info-label">Priority:</td>
            <td class="info-val"><strong>{{ $ticket->priority?->getLabel() ?? ucfirst($ticket->priority) }}</strong></td>
        </tr>
        <tr>
            <td class="info-label">Reporter Type:</td>
            <td class="info-val">{{ ucfirst($ticket->reporter_type) }}</td>
            <td class="info-label">Assigned Inspector:</td>
            <td class="info-val">{{ $inspector?->name ?? 'Dwelly Operations' }}</td>
        </tr>
        <tr>
            <td class="info-label">Assigned Vendor:</td>
            <td class="info-val">{{ $vendor?->name ?? 'Dwelly In-House / Direct' }}</td>
            <td class="info-label">Paying Party:</td>
            <td class="info-val"><strong>{{ $ticket->payer_type?->getLabel() ?? 'Pending Decision' }}</strong></td>
        </tr>
        @if(filled($ticket->description))
        <tr>
            <td class="info-label">Problem Description:</td>
            <td class="info-val" colspan="3">{{ $ticket->description }}</td>
        </tr>
        @endif
    </table>

    <!-- 2. Defect Items, Assessment & Repair Evidence Photos -->
    <div class="section-header">2. Defect Items, Assessment & Proof of Completed Repair</div>
    
    @if(empty($itemsData))
        <div style="padding: 10px; color: #64748b; font-style: italic;">No defect items recorded.</div>
    @else
        @foreach($itemsData as $item)
            <div class="item-card">
                <!-- Item Title Bar -->
                <table style="width: 100%; border-collapse: collapse; background: #f8fafc; border-bottom: 1.5px solid #cbd5e1;">
                    <tr>
                        <td style="padding: 5px 8px; font-weight: bold; font-size: 9.5px; color: #1e3a8a;">
                            Item #{{ $item['index'] }}: {{ $item['target_name'] }}
                        </td>
                        <td style="padding: 5px 8px; text-align: right; font-size: 8.5px; color: #64748b;">
                            Status: <strong style="color: #059669;">{{ strtoupper($item['status']) }}</strong>
                        </td>
                    </tr>
                </table>

                <!-- Issue & Resolution Details -->
                <table class="item-details-table">
                    <tr>
                        <td style="width: 50%; border-right: 1px solid #e2e8f0; background: #fafafa;">
                            <div style="font-size: 8px; font-weight: bold; color: #64748b; text-transform: uppercase;">
                                [REPORTED DEFECT / ASSESSMENT]
                            </div>
                            <div style="font-size: 9px; margin-top: 2px; color: #0f172a;">
                                {{ $item['issue_description'] ?: 'No defect description provided.' }}
                            </div>
                        </td>
                        <td style="width: 50%; background: #f0fdf4;">
                            <div style="font-size: 8px; font-weight: bold; color: #166534; text-transform: uppercase;">
                                [EXECUTED REPAIR & RESOLUTION NOTES]
                            </div>
                            <div style="font-size: 9px; margin-top: 2px; color: #166534; font-weight: bold;">
                                {{ $item['repair_action'] ?: 'Pending work resolution log.' }}
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- High-Clarity Visual Comparison: Before vs After -->
                <table class="evidence-comparison-table">
                    <tr>
                        <!-- Before Photos Column -->
                        <td>
                            <div class="evidence-pane-before">
                                <div class="pane-header-before">
                                    BEFORE REPAIR (INITIAL ISSUE) &bull; {{ count($item['before_photos']) }} Photo(s)
                                </div>
                                <div class="pane-body">
                                    @if(empty($item['before_photos']))
                                        <div class="no-photo-box">No before-repair photos attached.</div>
                                    @else
                                        @foreach($item['before_photos'] as $pIdx => $p)
                                            <div class="pane-photo">
                                                <img src="{{ $p['src'] }}" alt="Before Repair Evidence #{{ $pIdx + 1 }}" />
                                                <div class="pane-photo-label">Before Repair Photo #{{ $pIdx + 1 }}</div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </td>

                        <!-- After Photos Column -->
                        <td>
                            <div class="evidence-pane-after">
                                <div class="pane-header-after">
                                    AFTER REPAIR (COMPLETED FIX) &bull; {{ count($item['after_photos']) }} Photo(s)
                                </div>
                                <div class="pane-body">
                                    @if(empty($item['after_photos']))
                                        <div class="no-photo-box">No after-repair photos attached.</div>
                                    @else
                                        @foreach($item['after_photos'] as $pIdx => $p)
                                            <div class="pane-photo" style="border-color: #86efac;">
                                                <img src="{{ $p['src'] }}" alt="After Repair Proof #{{ $pIdx + 1 }}" />
                                                <div class="pane-photo-label" style="color: #166534;">After Repair Proof #{{ $pIdx + 1 }}</div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        @endforeach
    @endif

    <!-- 3. Approved Quotation & Financial Allocation -->
    <div class="section-header">3. Quotation & Financial Allocation</div>
    
    @if($quote && $quote->items()->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 6%;">#</th>
                    <th style="width: 44%;">Description</th>
                    <th style="width: 12%; text-align: center;">Qty</th>
                    <th style="width: 18%; text-align: right;">Unit Price (₹)</th>
                    <th style="width: 20%; text-align: right;">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($quote->items as $idx => $qItem)
                    @php
                        $itemAmount = (float) ($qItem->total_price ?? ($qItem->total_cost ?? ((float) $qItem->quantity * (float) $qItem->unit_price)));
                    @endphp
                    <tr>
                        <td>{{ $idx + 1 }}</td>
                        <td>
                            <strong>{{ $qItem->description ?: 'Maintenance Item' }}</strong>
                        </td>
                        <td style="text-align: center;">{{ number_format((float) $qItem->quantity, 2) }}</td>
                        <td style="text-align: right;">₹{{ number_format((float) $qItem->unit_price, 2) }}</td>
                        <td style="text-align: right;"><strong>₹{{ number_format($itemAmount, 2) }}</strong></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <!-- Quotation Financial Summary -->
        <table class="financial-container">
            <tr>
                <td style="width: 55%; vertical-align: top; padding-right: 12px;">
                    <div style="font-size: 8px; color: #64748b; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px 8px;">
                        <strong>Commercial Summary:</strong> Quotation reflects the approved scope of repair works and finalized costs. Financial responsibility assigned to <strong>{{ $ticket->payer_type?->getLabel() ?? 'Client' }}</strong>.
                    </div>
                </td>
                <td style="width: 45%; vertical-align: top;">
                    <table style="width: 100%; border-collapse: collapse; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 4px;">
                        <tr>
                            <td style="padding: 3px 6px; font-size: 8.5px; color: #64748b;">Subtotal:</td>
                            <td style="padding: 3px 6px; font-size: 8.5px; text-align: right;">₹{{ number_format((float) ($quote->subtotal_amount ?? $quote->items->sum('total_price')), 2) }}</td>
                        </tr>
                        @if((float) $quote->margin_amount > 0)
                        <tr>
                            <td style="padding: 3px 6px; font-size: 8.5px; color: #64748b;">Facilitation Margin:</td>
                            <td style="padding: 3px 6px; font-size: 8.5px; text-align: right;">₹{{ number_format((float) $quote->margin_amount, 2) }}</td>
                        </tr>
                        @endif
                        @if((float) $quote->tax_amount > 0)
                        <tr>
                            <td style="padding: 3px 6px; font-size: 8.5px; color: #64748b;">Taxes (GST):</td>
                            <td style="padding: 3px 6px; font-size: 8.5px; text-align: right;">₹{{ number_format((float) $quote->tax_amount, 2) }}</td>
                        </tr>
                        @endif
                        <tr class="total-row">
                            <td style="padding: 4px 6px; font-size: 9.5px; color: #1e3a8a;">Approved Grand Total:</td>
                            <td style="padding: 4px 6px; font-size: 9.5px; font-weight: bold; text-align: right; color: #1e3a8a;">₹{{ number_format((float) $quote->total_amount, 2) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    @else
        <table class="info-grid" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 6px;">
            <tr>
                <td class="info-label">Direct Settlement:</td>
                <td class="info-val">₹{{ number_format((float) $ticket->total_cost, 2) }}</td>
                <td class="info-label">Payer Decision:</td>
                <td class="info-val"><strong>{{ $ticket->payer_type?->getLabel() ?? 'Direct' }}</strong></td>
            </tr>
            @if(filled($ticket->direct_payment_reference))
            <tr>
                <td class="info-label">Payment Ref:</td>
                <td class="info-val" colspan="3">{{ $ticket->direct_payment_reference }}</td>
            </tr>
            @endif
        </table>
    @endif

    <!-- 4. Client Acceptance & Sign-off -->
    <div class="section-header">4. Paying Party Acceptance & Sign-Off</div>
    
    <table class="signoff-table">
        <tr>
            <!-- Left: Client Acceptance Record -->
            <td class="signoff-box" style="margin-right: 6px;">
                <div style="font-size: 9px; font-weight: bold; color: #059669; text-transform: uppercase; margin-bottom: 4px;">
                    [PAYING PARTY ACCEPTANCE CONFIRMATION]
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="font-size: 8px; color: #64748b; width: 40%; padding: 2px 0;">Accepted By:</td>
                        <td style="font-size: 8.5px; font-weight: bold; padding: 2px 0;">
                            {{ $ticket->client_accepted_by_name ?: ($ticket->payer_type?->value === 'owner' ? ($owner?->name ?? 'Property Owner') : ($tenant?->name ?? 'Tenant')) }}
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 8px; color: #64748b; padding: 2px 0;">Acceptance Date:</td>
                        <td style="font-size: 8.5px; padding: 2px 0;">
                            {{ $ticket->client_accepted_at?->format('d M Y, h:i A') ?? ($ticket->completed_at?->format('d M Y, h:i A') ?? 'Confirmed on Record') }}
                        </td>
                    </tr>
                    @if(filled($ticket->client_acceptance_notes))
                    <tr>
                        <td style="font-size: 8px; color: #64748b; padding: 2px 0;">Client Notes:</td>
                        <td style="font-size: 8px; padding: 2px 0;">{{ $ticket->client_acceptance_notes }}</td>
                    </tr>
                    @endif
                </table>

                @if(!empty($acceptanceProofs))
                    <div style="margin-top: 6px;">
                        <div style="font-size: 7.5px; font-weight: bold; color: #64748b; margin-bottom: 3px;">Documentary Proof Attachment:</div>
                        @foreach($acceptanceProofs as $proof)
                            <div style="border: 1px solid #cbd5e1; border-radius: 3px; padding: 3px; background: #ffffff; margin-bottom: 4px; text-align: center;">
                                <img src="{{ $proof['src'] }}" alt="Acceptance Proof" style="max-height: 160px; max-width: 100%; display: block; margin: 0 auto;" />
                            </div>
                        @endforeach
                    </div>
                @endif
            </td>

            <!-- Right: Quality Verification / Executive Sign-Off -->
            <td class="signoff-box">
                <div style="font-size: 9px; font-weight: bold; color: #1e3a8a; text-transform: uppercase; margin-bottom: 4px;">
                    [DWELLY OPERATIONS SIGN-OFF]
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="font-size: 8px; color: #64748b; width: 45%; padding: 2px 0;">Operations Manager:</td>
                        <td style="font-size: 8.5px; font-weight: bold; padding: 2px 0;">{{ auth()->user()?->name ?? 'Dwelly Admin' }}</td>
                    </tr>
                    <tr>
                        <td style="font-size: 8px; color: #64748b; padding: 2px 0;">Verification Audit:</td>
                        <td style="font-size: 8.5px; padding: 2px 0;">
                            @if($ticket->triggeredAudit)
                                Audit #{{ $ticket->triggeredAudit->audit_number }} ({{ $ticket->triggeredAudit->status?->getLabel() ?? 'Initiated' }})
                            @else
                                Direct Completion (Audit Waived)
                            @endif
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size: 8px; color: #64748b; padding: 2px 0;">Completed Date:</td>
                        <td style="font-size: 8.5px; padding: 2px 0;">
                            {{ $ticket->completed_at?->format('d M Y') ?? now()->format('d M Y') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

</body>
</html>
