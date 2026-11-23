<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Invoice - {{ $invoice->invoice_number }}</title>
    <style>
        @page {
            margin: 28px 32px;
        }
        * {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10.5px;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.45;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 18px;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 12px;
        }
        .brand-title {
            font-size: 24px;
            font-weight: bold;
            color: #0369a1;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .brand-subtitle {
            font-size: 9px;
            color: #64748b;
            margin-top: 2px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .agency-notice {
            font-size: 8.5px;
            color: #0369a1;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 4px;
            padding: 4px 8px;
            display: inline-block;
            margin-top: 6px;
        }
        .doc-title {
            font-size: 16px;
            font-weight: bold;
            color: #0f172a;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 0.75px;
            margin: 0;
        }
        .doc-sub {
            font-size: 9px;
            color: #64748b;
            text-align: right;
            margin-top: 2px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-paid { background: #dcfce7; color: #166534; }
        .badge-partial { background: #ffedd5; color: #9a3412; }
        .badge-cancelled { background: #f1f5f9; color: #475569; }

        .info-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 10px 0;
            margin-left: -10px;
            margin-right: -10px;
            margin-bottom: 16px;
        }
        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px 12px;
            vertical-align: top;
        }
        .card-header {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0284c7;
            margin-bottom: 6px;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 4px;
        }
        .card-row {
            margin-bottom: 3px;
        }
        .card-label {
            font-size: 8.5px;
            color: #64748b;
            display: inline-block;
            width: 80px;
        }
        .card-value {
            font-size: 9.5px;
            font-weight: bold;
            color: #0f172a;
        }

        .table-container {
            margin-bottom: 16px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }
        .data-table th {
            background: #0284c7;
            color: #ffffff;
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 7px 10px;
            text-align: left;
        }
        .data-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9.5px;
        }
        .data-table tr:nth-child(even) td {
            background: #fafafa;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .summary-wrapper {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .instructions-cell {
            width: 58%;
            vertical-align: top;
            padding-right: 14px;
        }
        .totals-cell {
            width: 42%;
            vertical-align: top;
        }

        .instructions-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 10px 12px;
        }
        .instructions-title {
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
            color: #166534;
            margin-bottom: 6px;
            border-bottom: 1px dashed #86efac;
            padding-bottom: 3px;
        }
        .inst-row {
            font-size: 8.5px;
            margin-bottom: 3px;
            color: #14532d;
        }
        .inst-label {
            display: inline-block;
            width: 90px;
            color: #166534;
            font-weight: bold;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .totals-table td {
            padding: 6px 10px;
            font-size: 9px;
        }
        .totals-table .grand-total-row td {
            background: #0284c7;
            color: #ffffff;
            font-weight: bold;
            font-size: 11px;
            padding: 8px 10px;
        }

        .legal-notice {
            margin-top: 14px;
            padding: 8px 10px;
            background: #f8fafc;
            border-left: 3px solid #0284c7;
            border-radius: 0 4px 4px 0;
            font-size: 8px;
            color: #64748b;
            line-height: 1.4;
        }

        .footer {
            margin-top: 14px;
            text-align: center;
            font-size: 8px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 8px;
        }
    </style>
</head>
<body>
    @php
        $category = \App\Domain\Finance\Services\BillingPropertyResolver::categorizeInvoice($invoice);
        $property = \App\Domain\Finance\Services\BillingPropertyResolver::resolveProperty($invoice);

        $maintenance = null;
        if ($invoice->reference_type === \App\Domain\Maintenance\Models\MaintenanceRequest::class && $invoice->reference_id) {
            $maintenance = \App\Domain\Maintenance\Models\MaintenanceRequest::with(['property.owner', 'owner', 'tenant', 'currentClientQuote'])->find($invoice->reference_id);
            if (!$property && $maintenance?->property) {
                $property = $maintenance->property;
            }
        }

        $owner = $property?->owner ?? $maintenance?->owner;
        if (!$owner && $property?->id) {
            $mouPartyId = \App\Domain\Mou\Models\Mou::where('property_id', $property->id)->whereNotNull('party_id')->latest()->value('party_id');
            if ($mouPartyId) {
                $owner = \App\Domain\Party\Models\Party::find($mouPartyId);
            }
        }

        $docTitle = match($category['key']) {
            'maintenance' => 'Maintenance Invoice',
            'fee' => 'Documentation Fee',
            'damage' => 'Move-Out Deduction',
            default => 'Tax Invoice',
        };

        $docSub = match($category['key']) {
            'maintenance' => 'Property Repairs & Operations Statement',
            'fee' => 'Agreement & Processing Charge',
            'damage' => 'Tenant Move-Out Settlement',
            default => 'Statement of Account',
        };

        $statusStr = is_object($invoice->status) ? $invoice->status->value : (string) ($invoice->status ?? 'draft');
        $badgeClass = match (strtolower($statusStr)) {
            'paid' => 'badge-paid',
            'partially_paid' => 'badge-partial',
            'cancelled' => 'badge-cancelled',
            default => 'badge-pending',
        };
        $statusLabel = match (strtolower($statusStr)) {
            'paid' => 'Settled / Paid',
            'partially_paid' => 'Partially Paid',
            'cancelled' => 'Cancelled',
            default => 'Payment Due',
        };

        $clientRole = 'Client';
        if ($maintenance) {
            $clientRole = ($maintenance->payer_type?->value ?? (string)$maintenance->payer_type) === 'tenant' ? 'Tenant' : 'Property Owner';
        }

        $amountPaid = (float) $invoice->amount_paid;
        $balanceDue = (float) $invoice->balance_due;
        $totalPayable = $balanceDue > 0 ? $balanceDue : (float) $invoice->grand_total;
    @endphp

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <h1 class="brand-title">DWELLY</h1>
                <div class="brand-subtitle">Property Management & Residential Operations</div>
                @if($owner)
                    <div class="agency-notice">
                        <strong>Managing Agent</strong> for Property Owner: <strong>{{ $owner->display_name ?? $owner->name }}</strong>
                    </div>
                @endif
            </td>
            <td style="width: 45%; vertical-align: top; text-align: right;">
                <div class="doc-title">{{ $docTitle }}</div>
                <div class="doc-sub">{{ $docSub }}</div>
                <div style="margin-top: 6px;">
                    <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Notice Metadata & Tenant/Property Info -->
    <table class="info-table">
        <tr>
            <!-- Reference Details -->
            <td style="width: 33.3%;" class="info-card">
                <div class="card-header">Invoice Reference</div>
                <div class="card-row">
                    <span class="card-label">Invoice Ref:</span>
                    <span class="card-value">#{{ $invoice->invoice_number }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Issue Date:</span>
                    <span class="card-value">{{ $invoice->issue_date ? $invoice->issue_date->format('d M Y') : now()->format('d M Y') }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Due Date:</span>
                    <span class="card-value" style="color: #dc2626;">{{ $invoice->due_date ? $invoice->due_date->format('d M Y') : 'Due upon receipt' }}</span>
                </div>
                @if($maintenance)
                    <div class="card-row">
                        <span class="card-label">Ticket Ref:</span>
                        <span class="card-value">#{{ $maintenance->ticket_number }}</span>
                    </div>
                @else
                    <div class="card-row">
                        <span class="card-label">Status:</span>
                        <span class="card-value">{{ ucfirst($statusStr) }}</span>
                    </div>
                @endif
            </td>

            <!-- Client / Billed To Details -->
            <td style="width: 33.3%;" class="info-card">
                <div class="card-header">Billed To ({{ $clientRole }})</div>
                <div class="card-row">
                    <span class="card-label">Name:</span>
                    <span class="card-value">{{ $invoice->contact?->name ?? 'Valued Client' }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Phone:</span>
                    <span class="card-value">{{ $invoice->contact?->phone ?? '—' }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Email:</span>
                    <span class="card-value">{{ $invoice->contact?->email ?? '—' }}</span>
                </div>
                @if($invoice->contact?->gstin)
                    <div class="card-row">
                        <span class="card-label">GSTIN:</span>
                        <span class="card-value">{{ $invoice->contact->gstin }}</span>
                    </div>
                @else
                    <div class="card-row">
                        <span class="card-label">Account:</span>
                        <span class="card-value">Client A/C</span>
                    </div>
                @endif
            </td>

            <!-- Property Details -->
            <td style="width: 33.3%;" class="info-card">
                <div class="card-header">Property Details</div>
                <div class="card-row">
                    <span class="card-label">Property:</span>
                    <span class="card-value">{{ $property?->building_name ?? $property?->name ?? 'Residential Property' }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Unit/Door:</span>
                    <span class="card-value">{{ $property?->unit_number ?? $property?->door_number ?? 'Main Unit' }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Address:</span>
                    <span class="card-value">{{ Str::limit($property ? implode(', ', array_filter([$property->address_line_1, $property->localityRef?->name ?? $property->locality, $property->city])) : 'On File', 30) }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Property Code:</span>
                    <span class="card-value">{{ $property?->code ?? '—' }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Line Items Table -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 8%;" class="text-center">#</th>
                    <th style="width: 52%;">Charge Description / Scope of Work</th>
                    <th style="width: 12%;" class="text-center">Period/Qty</th>
                    <th style="width: 14%;" class="text-right">Rate (₹)</th>
                    <th style="width: 14%;" class="text-right">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($invoice->items as $idx => $item)
                    <tr>
                        <td class="text-center" style="color: #64748b;">{{ $idx + 1 }}</td>
                        <td>
                            <strong>{{ $item->description }}</strong>
                            @if($item->hsn_sac_code)
                                <span style="color: #64748b; font-size: 8.5px;"> (HSN/SAC: {{ $item->hsn_sac_code }})</span>
                            @endif
                        </td>
                        <td class="text-center">{{ (int) ($item->quantity ?? 1) }}</td>
                        <td class="text-right">₹{{ number_format($item->unit_price, 2) }}</td>
                        <td class="text-right" style="font-weight: bold;">₹{{ number_format($item->line_total, 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="text-center" style="color: #64748b; padding: 14px;">
                            Charges: ₹{{ number_format($invoice->grand_total, 2) }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <!-- Summary & Payment Instructions Grid -->
    <table class="summary-wrapper">
        <tr>
            <!-- Payment Instructions -->
            <td class="instructions-cell">
                <div class="instructions-box">
                    <div class="instructions-title">Payment & Escrow Instructions</div>
                    <div class="inst-row">
                        <span class="inst-label">Beneficiary:</span>
                        <strong>Dwelly Client / Escrow Account</strong>
                    </div>
                    <div class="inst-row">
                        <span class="inst-label">Bank Name:</span>
                        <span>ICICI Bank</span>
                    </div>
                    <div class="inst-row">
                        <span class="inst-label">Account No:</span>
                        <strong>123405009876</strong>
                    </div>
                    <div class="inst-row">
                        <span class="inst-label">IFSC Code:</span>
                        <span>ICIC0001234</span>
                    </div>
                    <div class="inst-row">
                        <span class="inst-label">UPI VPA:</span>
                        <strong>dwelly.rent@icici</strong>
                    </div>
                    <div style="font-size: 8px; color: #166534; margin-top: 6px; border-top: 1px dashed #bbf7d0; padding-top: 4px;">
                        * Please mention your Invoice Ref <strong>#{{ $invoice->invoice_number }}</strong> in payment remarks.
                    </div>
                </div>
            </td>

            <!-- Totals & Statement Balance Card -->
            <td class="totals-cell">
                <table class="totals-table">
                    <tr>
                        <td style="color: #64748b;">Current Invoice Charges:</td>
                        <td class="text-right" style="font-weight: bold;">₹{{ number_format((float) $invoice->grand_total, 2) }}</td>
                    </tr>
                    @if((float) $invoice->tax_total > 0)
                        <tr>
                            <td style="color: #64748b;">Tax Total:</td>
                            <td class="text-right" style="font-weight: bold;">₹{{ number_format((float) $invoice->tax_total, 2) }}</td>
                        </tr>
                    @endif
                    @if((float) $invoice->discount_amount > 0)
                        <tr>
                            <td style="color: #16a34a;">Discount Applied:</td>
                            <td class="text-right" style="font-weight: bold; color: #16a34a;">- ₹{{ number_format((float) $invoice->discount_amount, 2) }}</td>
                        </tr>
                    @endif
                    @if($amountPaid > 0)
                        <tr>
                            <td style="color: #16a34a;">Payments Received:</td>
                            <td class="text-right" style="font-weight: bold; color: #16a34a;">- ₹{{ number_format($amountPaid, 2) }}</td>
                        </tr>
                    @endif
                    <tr class="grand-total-row">
                        <td>TOTAL DUE:</td>
                        <td class="text-right">₹{{ number_format($totalPayable, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Notice & Disclosure -->
    <div class="legal-notice">
        <strong>Notice & Statement Disclosure:</strong> This document is an operational invoice and statement of account issued by Dwelly as the authorized Managing Agent on behalf of the Property Owner for authorized property operations, maintenance, repairs and turnover services. Official payment receipts are generated and dispatched immediately upon receipt and clearance of funds.
    </div>

    <div class="footer">
        Dwelly Property Management • Questions or support? Contact us at finance@dwelly.in • Document Ref: #{{ $invoice->invoice_number }}
    </div>
</body>
</html>
