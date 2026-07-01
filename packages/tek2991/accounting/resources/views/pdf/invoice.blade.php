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
            margin-bottom: 30px;
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
            padding: 12px;
            border-bottom: 2px solid #e2e8f0;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
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
            padding: 8px 12px;
            text-align: right;
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
            margin-top: 60px;
            padding-top: 20px;
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
        $settings = \Tek2991\Accounting\Models\Setting::where('company_id', $invoice->company_id)->first();
    @endphp

    <table class="header">
        <tr>
            <td style="width: 50%;">
                <div class="company-name">{{ $settings->company_name ?? $invoice->company->name ?? 'Our Company' }}</div>
                @if($settings)
                    @if($settings->company_address)
                        <div style="color: #4a5568;">{!! nl2br(e($settings->company_address)) !!}</div>
                    @endif
                    @if($settings->company_phone || $settings->company_email)
                        <div style="color: #4a5568; margin-top: 5px;">
                            @if($settings->company_phone) {{ $settings->company_phone }} @endif
                            @if($settings->company_phone && $settings->company_email) <br> @endif
                            @if($settings->company_email) {{ $settings->company_email }} @endif
                        </div>
                    @endif
                    @if($settings->company_tax_id)
                        <div style="color: #4a5568; margin-top: 5px; font-weight: bold;">
                            GSTIN/Tax ID: {{ $settings->company_tax_id }}
                        </div>
                    @endif
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

    <div class="bill-to">
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

    <table class="items-table">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-center">Qty</th>
                <th class="text-right">Unit Price</th>
                <th class="text-right">Tax</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
                <tr>
                    <td>
                        @if($item->item)
                            <strong>{{ $item->item->name }}</strong>
                            @if($item->description && trim($item->description) !== trim($item->item->name))
                                <br><span style="color: #4a5568; font-size: 11px;">{!! nl2br(e($item->description)) !!}</span>
                            @endif
                        @else
                            <strong>{{ $item->description }}</strong>
                        @endif
                        @if($item->item && $item->item->hsn_sac_code)
                            <br><span style="color: #718096; font-size: 11px;">HSN/SAC: {{ $item->item->hsn_sac_code }}</span>
                        @endif
                    </td>
                    <td class="text-center">{{ number_format($item->quantity, 2) }}</td>
                    <td class="text-right">{{ $currency }} {{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">{{ $currency }} {{ number_format($item->tax_amount, 2) }}</td>
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
            @if($invoice->notes)
                <div style="margin-bottom: 15px;">
                    <strong>Notes:</strong><br>
                    {!! nl2br(e($invoice->notes)) !!}
                </div>
            @endif
            
            @if($invoice->terms)
                <div>
                    <strong>Terms & Conditions:</strong><br>
                    {!! nl2br(e($invoice->terms)) !!}
                </div>
            @endif
        </div>
    @endif
</body>
</html>
