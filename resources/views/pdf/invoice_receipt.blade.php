<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Payment Receipt - {{ $payment->reference ?? 'PAY-' . $invoice->invoice_number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            color: #2b2b2b;
            margin: 0;
            padding: 20px;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .logo-title {
            font-size: 24px;
            font-weight: bold;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .company-subtitle {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        .receipt-title {
            font-size: 20px;
            font-weight: bold;
            color: #166534;
            text-align: right;
            text-transform: uppercase;
        }
        .receipt-meta {
            font-size: 12px;
            color: #475569;
            text-align: right;
            margin-top: 5px;
        }
        .receipt-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 25px;
        }
        .row {
            margin-bottom: 12px;
        }
        .label {
            font-weight: bold;
            color: #166534;
            display: inline-block;
            width: 160px;
        }
        .value {
            color: #0f172a;
        }
        .amount-card {
            background: #ffffff;
            border: 2px solid #166534;
            padding: 12px 20px;
            border-radius: 6px;
            text-align: center;
            margin-top: 15px;
        }
        .amount-num {
            font-size: 24px;
            font-weight: bold;
            color: #166534;
        }
        .seal-box {
            float: right;
            width: 180px;
            border: 2px dashed #166534;
            color: #166534;
            padding: 10px;
            text-align: center;
            font-weight: bold;
            font-size: 11px;
            border-radius: 6px;
            margin-top: 20px;
        }
        .clear { clear: both; }
        .footer {
            margin-top: 40px;
            border-top: 1px solid #cbd5e1;
            padding-top: 15px;
            text-align: center;
            font-size: 11px;
            color: #64748b;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td>
                <div class="logo-title">DWELLY</div>
                <div class="company-subtitle">Official Payment Receipt</div>
            </td>
            <td>
                <div class="receipt-title">PAYMENT RECEIPT</div>
                <div class="receipt-meta">
                    <strong>Payment Ref #:</strong> {{ $payment->reference ?? ('PAY-' . $payment->id) }}<br>
                    <strong>Invoice #:</strong> {{ $invoice->invoice_number }}<br>
                    <strong>Payment Date:</strong> {{ $payment->payment_date ? $payment->payment_date->format('d M Y') : date('d M Y') }}
                </div>
            </td>
        </tr>
    </table>

    <div class="receipt-box">
        <div class="row">
            <span class="label">Received From:</span>
            <span class="value"><strong>{{ $invoice->contact?->name ?? 'Customer' }}</strong></span>
        </div>
        <div class="row">
            <span class="label">Invoice Reference:</span>
            <span class="value">{{ $invoice->notes ?? $invoice->invoice_number }}</span>
        </div>
        <div class="row">
            <span class="label">Payment Account:</span>
            <span class="value">{{ $payment->paymentAccount?->name ?? 'Bank / Cash Account' }}</span>
        </div>
        @if($payment->reference)
        <div class="row">
            <span class="label">Transaction Ref / UTR:</span>
            <span class="value">{{ $payment->reference }}</span>
        </div>
        @endif

        <div class="amount-card">
            <div style="font-size: 11px; color: #64748b; text-transform: uppercase; font-weight: bold;">Amount Received</div>
            <div class="amount-num">₹ {{ number_format($payment->amount, 2) }}</div>
        </div>
    </div>

    <div class="seal-box">
        DWELLY VERIFIED<br>
        <span style="font-size: 9px; font-weight: normal;">Payment Received & Confirmed</span>
    </div>
    <div class="clear"></div>

    <div class="footer">
        Thank you for your payment! This is an electronically generated official receipt.<br>
        Dwelly Property Management Solutions
    </div>

</body>
</html>
