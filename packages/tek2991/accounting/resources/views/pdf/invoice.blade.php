<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: 'Helvetica Neue', 'Helvetica', Arial, sans-serif;
            color: #333;
            font-size: 13px;
            line-height: 1.5;
            margin: 0;
            padding: 30px;
        }
        .header {
            width: 100%;
            margin-bottom: 20px;
        }
        .header td {
            vertical-align: top;
        }
        .company-name {
            font-size: 28px;
            font-weight: bold;
            color: #1a202c;
            margin-bottom: 5px;
        }
        .invoice-title {
            font-size: 36px;
            color: #cbd5e0;
            text-align: right;
            text-transform: uppercase;
            font-weight: bold;
            margin-bottom: 10px;
            letter-spacing: 2px;
        }
        .meta-data {
            float: right;
            width: 250px;
        }
        .meta-data table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-data th {
            text-align: right;
            padding: 4px 10px 4px 0;
            color: #718096;
            font-weight: normal;
        }
        .meta-data td {
            text-align: right;
            font-weight: bold;
            padding: 4px 0;
        }
        .bill-to {
            margin-bottom: 40px;
        }
        .bill-to h3 {
            margin-top: 0;
            color: #718096;
            font-size: 12px;
            text-transform: uppercase;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 5px;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 30px;
        }
        .items-table th {
            background-color: #f7fafc;
            color: #4a5568;
            font-weight: bold;
            text-align: left;
            padding: 8px;
            border-bottom: 2px solid #e2e8f0;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
        }
        .items-table td.text-right, .items-table td.text-center {
            white-space: nowrap;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }
        .summary-wrapper {
            width: 100%;
        }
        .summary-table {
            width: 300px;
            float: right;
            border-collapse: collapse;
        }
        .summary-table th, .summary-table td {
            padding: 6px 8px;
            text-align: right;
        }
        .summary-table td {
            white-space: nowrap;
        }
        .summary-table th {
            color: #718096;
            font-weight: normal;
        }
        .summary-table .grand-total th, .summary-table .grand-total td {
            font-weight: bold;
            font-size: 16px;
            border-top: 2px solid #e2e8f0;
            border-bottom: 2px solid #e2e8f0;
            color: #1a202c;
            background-color: #f7fafc;
        }
        .summary-table .balance-due th, .summary-table .balance-due td {
            font-weight: bold;
            color: #e53e3e;
            padding-top: 12px;
        }
        .notes-section {
            clear: both;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            color: #4a5568;
            font-size: 12px;
        }
        .footer {
            position: fixed;
            bottom: 20px;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 10px;
            color: #a0aec0;
            width: 100%;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            background: #edf2f7;
            color: #4a5568;
            margin-top: 5px;
        }
        .badge-paid { background: #c6f6d5; color: #22543d; }
        .badge-overdue { background: #fed7d7; color: #822727; }
        .badge-draft { background: #e2e8f0; color: #4a5568; }
        .badge-sent { background: #bee3f8; color: #2c5282; }
    </style>
</head>
<body>
    @if(isset($__pdf_driver) && $__pdf_driver === 'dompdf')
        <script type="text/php">
            if (isset($pdf)) {
                $text = "Page {PAGE_NUM} of {PAGE_COUNT}";
                $size = 10;
                $font = $fontMetrics->getFont("Helvetica");
                $width = $fontMetrics->get_text_width($text, $font, $size) / 2;
                $x = ($pdf->get_width() - $width) / 2;
                $y = $pdf->get_height() - 35;
                $pdf->page_text($x, $y, $text, $font, $size, array(0.6, 0.6, 0.6));
            }
        </script>
        <div class="footer">
            Generated using <strong>dompdf</strong> driver (Dev/Local Mode)
        </div>
    @endif

    @php
        $currency = \Tek2991\Accounting\Enums\CurrencySymbol::getSymbol($invoice->currency_code ?? 'USD');
        $statusClass = match($invoice->display_status) {
            'paid' => 'badge-paid',
            'overdue' => 'badge-overdue',
            'sent' => 'badge-sent',
            default => 'badge-draft'
        };
        $branch = $invoice->branch;
        $organization = $branch ? $branch->organization : \Tek2991\Accounting\Models\Organization::current();
        
        $companyName = $organization->name ?? 'Our Company';
        $companyEmail = $organization->email ?? '';
        $companyPhone = $organization->phone ?? '';
        $companyTaxId = $organization->pan ?? '';
        
        $branchName = $branch ? $branch->name : null;
        $branchAddress = $branch ? implode(', ', array_filter([$branch->address, $branch->city, $branch->state?->name, $branch->postal_code])) : '';
        $branchPhone = $branch->phone ?? '';
        $branchEmail = $branch->email ?? '';
        $branchTaxId = $branch?->gstRegistration?->gstin ?? '';
    @endphp

    <table class="header">
        <tr>
            <td style="width: 50%;">
                <div class="company-name">{{ $companyName }}</div>
                @if($companyPhone || $companyEmail)
                    <div style="color: #4a5568; margin-top: 5px;">
                        @if($companyPhone) {{ $companyPhone }} @endif
                        @if($companyPhone && $companyEmail) | @endif
                        @if($companyEmail) {{ $companyEmail }} @endif
                    </div>
                @endif
                @if($companyTaxId)
                    <div style="color: #4a5568; margin-top: 5px; font-weight: bold;">
                        PAN: {{ $companyTaxId }}
                    </div>
                @endif

                @if($branchName)
                    <div style="margin-top: 15px; padding-top: 10px; border-top: 1px solid #e2e8f0;">
                        <strong style="color: #2d3748; font-size: 14px;">Branch: {{ $branchName }}</strong>
                        @if($branchAddress)
                            <div style="color: #4a5568; margin-top: 5px;">{!! nl2br(e($branchAddress)) !!}</div>
                        @endif
                        @if($branchPhone || $branchEmail)
                            <div style="color: #4a5568; margin-top: 5px;">
                                @if($branchPhone) {{ $branchPhone }} @endif
                                @if($branchPhone && $branchEmail) <br> @endif
                                @if($branchEmail) {{ $branchEmail }} @endif
                            </div>
                        @endif
                        @if($branchTaxId)
                            <div style="color: #4a5568; margin-top: 5px; font-weight: bold;">
                                GSTIN: {{ $branchTaxId }}
                            </div>
                        @endif
                    </div>
                @endif
            </td>
            <td style="width: 50%;">
                <div class="invoice-title">Invoice</div>
                <div class="meta-data">
                    <table>
                        <tr>
                            <th>Invoice #</th>
                            <td>{{ $invoice->invoice_number }}</td>
                        </tr>
                        <tr>
                            <th>Issue Date</th>
                            <td>{{ $invoice->issue_date ? $invoice->issue_date->format('Y-m-d') : 'N/A' }}</td>
                        </tr>
                        <tr>
                            <th>Due Date</th>
                            <td>{{ $invoice->due_date ? $invoice->due_date->format('Y-m-d') : 'N/A' }}</td>
                        </tr>
                        <tr>
                            <td colspan="2" style="text-align: right; padding-top: 10px;">
                                <span class="badge {{ $statusClass }}">{{ str_replace('_', ' ', strtoupper($invoice->display_status)) }}</span>
                            </td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table style="width: 100%; margin-bottom: 20px; border-collapse: collapse;">
        <tr>
            <td style="width: 50%; vertical-align: top; padding-right: 20px;">
                <div class="bill-to" style="margin-bottom: 0;">
                    <h3>Bill To</h3>
                    <strong>{{ $invoice->contact->name }}</strong><br>
                    @if($invoice->contact->billing_address)
                        <div style="margin-bottom: 5px;">
                            @if(is_array($invoice->contact->billing_address))
                                @foreach($invoice->contact->billing_address as $line)
                                    {{ $line }}<br>
                                @endforeach
                            @else
                                {!! nl2br(e($invoice->contact->billing_address)) !!}<br>
                            @endif
                        </div>
                    @endif
                    @if($invoice->contact->email)
                        {{ $invoice->contact->email }}<br>
                    @endif
                    @if($invoice->contact->phone)
                        {{ $invoice->contact->phone }}<br>
                    @endif
                    @if($invoice->contact->gstin)
                        <div style="margin-top: 5px; font-weight: bold;">GSTIN/Tax ID: {{ $invoice->contact->gstin }}</div>
                    @endif
                </div>
            </td>
            <td style="width: 50%; vertical-align: top; padding-left: 20px;">
                @if($invoice->contact->shipping_address)
                    <div class="bill-to" style="margin-bottom: 0;">
                        <h3>Ship To</h3>
                        <strong>{{ $invoice->contact->name }}</strong><br>
                        <div style="margin-bottom: 5px;">
                            @if(is_array($invoice->contact->shipping_address))
                                @foreach($invoice->contact->shipping_address as $line)
                                    {{ $line }}<br>
                                @endforeach
                            @else
                                {!! nl2br(e($invoice->contact->shipping_address)) !!}<br>
                            @endif
                        </div>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-right">Unit Price</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Net Amount</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Tax Amount</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>
                        @if($item->item)
                            <strong>{{ $item->item->name }}</strong>
                            @if($item->hsn_sac_code || $item->item->hsn_sac)
                                <span style="color: #718096; font-size: 11px; margin-left: 5px;">(HSN/SAC: {{ $item->hsn_sac_code ?: $item->item->hsn_sac }})</span>
                            @endif
                            @if($item->description && trim($item->description) !== trim($item->item->name))
                                <br><span style="color: #4a5568; font-size: 11px;">{!! nl2br(e($item->description)) !!}</span>
                            @endif
                        @else
                            <strong>{{ $item->description }}</strong>
                            @if($item->hsn_sac_code)
                                <span style="color: #718096; font-size: 11px; margin-left: 5px;">(HSN/SAC: {{ $item->hsn_sac_code }})</span>
                            @endif
                        @endif
                    </td>
                    <td class="text-right">{{ $currency }} {{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right">{{ $currency }} {{ number_format($item->unit_price * $item->quantity, 2) }}</td>
                    <td class="text-right">
                        @if(!empty($item->tax_snapshot))
                            @foreach($item->tax_snapshot as $taxComp)
                                <div style="font-size: 11px; margin-bottom: 2px; white-space: nowrap;">
                                    {{ $taxComp['name'] ?? 'Tax' }} ({{ isset($taxComp['rate']) ? (float) $taxComp['rate'] : 0 }}%)
                                </div>
                            @endforeach
                        @elseif($item->tax)
                            <div style="font-size: 11px; margin-bottom: 2px;">
                                {{ $item->tax->name }} ({{ (float) $item->tax->total_rate }}%)
                            </div>
                        @else
                            -
                        @endif
                    </td>
                    <td class="text-right">
                        @if(!empty($item->tax_snapshot))
                            @foreach($item->tax_snapshot as $taxComp)
                                <div style="font-size: 11px; margin-bottom: 2px; white-space: nowrap;">
                                    {{ $currency }} {{ number_format(($taxComp['amount'] ?? 0) / 100, 2) }}
                                </div>
                            @endforeach
                        @else
                            <div style="font-size: 11px; margin-bottom: 2px; white-space: nowrap;">
                                {{ $item->tax_amount > 0 ? $currency . ' ' . number_format($item->tax_amount, 2) : '-' }}
                            </div>
                        @endif
                    </td>
                    <td class="text-right">{{ $currency }} {{ number_format($item->line_total + $item->tax_amount, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="clearfix summary-wrapper">
        <table class="summary-table">
            <tr>
                <th>Subtotal:</th>
                <td>{{ $currency }} {{ number_format($invoice->subtotal, 2) }}</td>
            </tr>
            @if($invoice->discount_amount > 0)
                <tr>
                    <th>Discount:</th>
                    <td style="color: #e53e3e;">-{{ $currency }} {{ number_format($invoice->discount_amount, 2) }}</td>
                </tr>
            @endif
            @if($invoice->tax_total > 0)
                <tr>
                    <th>Tax Total:</th>
                    <td>{{ $currency }} {{ number_format($invoice->tax_total, 2) }}</td>
                </tr>
            @endif
            <tr class="grand-total">
                <th>Grand Total:</th>
                <td>{{ $currency }} {{ number_format($invoice->grand_total, 2) }}</td>
            </tr>
            @if($invoice->amount_paid > 0)
                <tr>
                    <th>Amount Paid:</th>
                    <td>{{ $currency }} {{ number_format($invoice->amount_paid, 2) }}</td>
                </tr>
            @endif
            <tr class="balance-due">
                <th>Balance Due:</th>
                <td>{{ $currency }} {{ number_format($invoice->balance_due, 2) }}</td>
            </tr>
        </table>
    </div>

    @if($invoice->notes || $invoice->terms)
        <div class="notes-section">
            <table style="width: 100%; border-collapse: collapse;">
                @if($invoice->notes)
                    <tr>
                        <td style="width: 140px; vertical-align: top; padding-bottom: 10px;">
                            <strong>Notes:</strong>
                        </td>
                        <td style="vertical-align: top; padding-bottom: 10px;">
                            {!! nl2br(e($invoice->notes)) !!}
                        </td>
                    </tr>
                @endif
                
                @if($invoice->terms)
                    <tr>
                        <td style="width: 140px; vertical-align: top;">
                            <strong>Terms & Conditions:</strong>
                        </td>
                        <td style="vertical-align: top;">
                            {!! nl2br(e($invoice->terms)) !!}
                        </td>
                    </tr>
                @endif
            </table>
        </div>
    @endif

    <div style="page-break-inside: avoid;">
        <div style="margin-top: 10px; display: table; width: 100%;">
            <div style="display: table-cell; width: 50%;"></div>
            <div style="display: table-cell; width: 50%; text-align: right;">
                <p style="margin-bottom: 25px; color: #4a5568;">For <strong>{{ $companyName }}</strong></p>
                <p style="border-top: 1px solid #718096; display: inline-block; padding-top: 5px; color: #4a5568;">Authorised Signatory</p>
            </div>
        </div>
    </div>
</body>
</html>
