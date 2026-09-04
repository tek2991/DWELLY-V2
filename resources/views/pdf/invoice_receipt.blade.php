<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Payment Receipt - {{ $payment->reference ?? ('REC-' . $payment->id) }}</title>
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
            color: #166534;
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
            color: #166534;
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
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }
        
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
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
            color: #166534;
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
            width: 110px;
            font-weight: bold;
        }
        .info-value {
            color: #0f172a;
        }

        .amount-banner {
            background: #f0fdf4;
            border: 2px solid #166534;
            border-radius: 6px;
            padding: 14px 18px;
            text-align: center;
            margin-bottom: 18px;
        }
        .amount-banner-title {
            font-size: 10px;
            font-weight: bold;
            color: #166534;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }
        .amount-banner-val {
            font-size: 26px;
            font-weight: bold;
            color: #166534;
            letter-spacing: -0.5px;
        }

        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .summary-table th {
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
        .summary-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
            font-size: 10.5px;
        }
        .text-right {
            text-align: right !important;
        }
        .text-center {
            text-align: center !important;
        }

        .seal-box {
            border: 2px dashed #166534;
            background: #f0fdf4;
            color: #166534;
            padding: 8px 14px;
            text-align: center;
            font-weight: bold;
            font-size: 10px;
            border-radius: 6px;
            display: inline-block;
        }

        .signatory-box {
            text-align: right;
            font-size: 10.5px;
            color: #334155;
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
        $branch = $invoice->branch;
        $organization = $branch ? $branch->organization : \Tek2991\Accounting\Models\Organization::current();
        $companyName = $organization->name ?? 'Dwelly';
        $branchName = $branch ? $branch->name : null;
        $branchAddress = $branch ? implode(', ', array_filter([$branch->address, $branch->city, $branch->state?->name, $branch->postal_code])) : '';
        $branchPhone = $branch->phone ?? ($organization->phone ?? '');
        $branchEmail = $branch->email ?? ($organization->email ?? '');
        $branchGstin = $branch?->gstRegistration?->gstin ?? ($organization->gstin ?? '');

        $receiptNo = $payment->reference ? $payment->reference : 'REC-' . str_pad($payment->id, 6, '0', STR_PAD_LEFT);
    @endphp

    {{-- Header --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="vertical-align: top; width: 50%;">
                <div class="logo-title">{{ strtoupper($companyName) }}</div>
                <div class="company-subtitle">Property Management &bull; Payment Receipt</div>
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
                <div class="doc-title" style="margin-bottom: 6px;">Payment Receipt</div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Receipt Number:</td>
                        <td style="text-align: right; font-weight: bold; font-size: 10.5px; padding: 2px 0 2px 8px; width: 140px;">{{ $receiptNo }}</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Demand / Ref No:</td>
                        <td style="text-align: right; font-weight: bold; font-size: 10.5px; padding: 2px 0 2px 8px;">{{ $invoice->invoice_number }}</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Payment Date:</td>
                        <td style="text-align: right; font-weight: bold; font-size: 10.5px; padding: 2px 0 2px 8px;">{{ $payment->payment_date ? $payment->payment_date->format('d M Y') : date('d M Y') }}</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Status:</td>
                        <td style="text-align: right; padding: 2px 0 2px 8px;">
                            <span class="badge badge-success">PAYMENT RECEIVED</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Clean Divider Line --}}
    <div style="width: 100%; height: 2px; background: #166534; margin-bottom: 18px;"></div>

    {{-- Info Grid --}}
    <table class="info-grid">
        <tr>
            <td class="info-box">
                <div class="box-title">Received From (Payer)</div>
                <div style="font-weight: bold; font-size: 11px; color: #0f172a; margin-bottom: 4px;">
                    {{ $invoice->contact?->name ?: 'Customer / Tenant' }}
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
            </td>
            <td style="width: 4%;"></td>
            <td class="info-box">
                <div class="box-title">Payment &amp; Account Details</div>
                <div class="info-row">
                    <span class="info-label">Deposit Account:</span>
                    <span class="info-value" style="font-weight: bold;">{{ $payment->paymentAccount?->name ?? 'Bank / Cash Account' }}</span>
                </div>
                @if($payment->reference)
                    <div class="info-row">
                        <span class="info-label">Ref / UTR Number:</span>
                        <span class="info-value" style="font-weight: bold;">{{ $payment->reference }}</span>
                    </div>
                @endif
                <div class="info-row">
                    <span class="info-label">Demand Purpose:</span>
                    <span class="info-value">{{ $invoice->notes ?? "Rent Demand {$invoice->invoice_number}" }}</span>
                </div>
                @if($payment->notes)
                    <div class="info-row">
                        <span class="info-label">Remarks:</span>
                        <span class="info-value">{{ $payment->notes }}</span>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Amount Banner --}}
    <div class="amount-banner">
        <div class="amount-banner-title">Total Amount Received</div>
        <div class="amount-banner-val">&#8377; {{ number_format((float) $payment->amount, 2) }}</div>
    </div>

    {{-- Settlement Breakdown Table --}}
    <table class="summary-table">
        <thead>
            <tr>
                <th style="width: 40%;">Demand / Charge Reference</th>
                <th class="text-right" style="width: 20%;">Total Demand Amt</th>
                <th class="text-right" style="width: 20%;">This Payment</th>
                <th class="text-right" style="width: 20%;">Remaining Balance</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>Ref #{{ $invoice->invoice_number }}</strong>
                    <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">
                        {{ $invoice->notes ?? 'Rent / Maintenance Settlement' }}
                    </div>
                </td>
                <td class="text-right">&#8377; {{ number_format((float) $invoice->grand_total, 2) }}</td>
                <td class="text-right" style="font-weight: bold; color: #166534;">&#8377; {{ number_format((float) $payment->amount, 2) }}</td>
                <td class="text-right" style="font-weight: bold; color: {{ (float) $invoice->balance_due > 0 ? '#b91c1c' : '#166534' }};">
                    &#8377; {{ number_format((float) $invoice->balance_due, 2) }}
                </td>
            </tr>
        </tbody>
    </table>

    {{-- Verification & Signatory Section --}}
    <table style="width: 100%; border-collapse: collapse; margin-top: 15px;">
        <tr>
            <td style="width: 50%; vertical-align: middle;">
                <div class="seal-box">
                    <span style="font-size: 11px; letter-spacing: 0.5px;">&#10004; DWELLY VERIFIED</span><br>
                    <span style="font-size: 8.5px; font-weight: normal; color: #15803d; margin-top: 2px; display: inline-block;">
                        Payment Received &amp; Confirmed
                    </span>
                </div>
            </td>
            <td style="width: 50%; vertical-align: bottom; text-align: right;">
                <div class="signatory-box">
                    <div style="font-weight: bold; margin-bottom: 35px;">For {{ $companyName }}</div>
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
                Thank you for your payment! This is an electronically generated official receipt. For inquiries, contact {{ $branchEmail ?: ($organization->email ?? 'support@dwelly.in') }}.
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                Generated: {{ now()->format('d M Y, h:i A') }}
            </td>
        </tr>
    </table>
</body>
</html>
