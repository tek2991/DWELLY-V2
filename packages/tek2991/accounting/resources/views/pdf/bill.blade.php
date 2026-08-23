<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Vendor Bill - {{ $bill->bill_number }}</title>
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
            color: #78350f;
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
            color: #b45309;
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
        .badge-received { background: #e0f2fe; color: #0369a1; }
        .badge-approved { background: #fef3c7; color: #92400e; }
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
            color: #78350f;
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
            font-weight: 600;
            color: #0f172a;
        }
        .grand-total-row td {
            font-weight: bold;
            font-size: 13px;
            border-top: 2px solid #cbd5e1;
            border-bottom: 2px solid #cbd5e1;
            color: #78350f;
            background-color: #fef3c7;
            padding: 6px 8px;
        }
        .balance-due-row td {
            font-weight: bold;
            font-size: 12px;
            color: #b91c1c;
            padding: 6px 8px;
        }

        .notes-box {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-left: 4px solid #f59e0b;
            border-radius: 4px;
            padding: 10px 12px;
            margin-top: 15px;
            font-size: 10px;
            color: #78350f;
            clear: both;
        }
        .notes-title {
            font-weight: bold;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .footer-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 9.5px;
            color: #64748b;
        }
    </style>
</head>
<body>
    @php
        $statusStr = $bill->status instanceof \BackedEnum ? $bill->status->value : (string) ($bill->status ?? 'received');
        $statusClass = match(strtolower($statusStr)) {
            'paid' => 'badge-paid',
            'approved' => 'badge-approved',
            'overdue' => 'badge-overdue',
            default => 'badge-received'
        };

        $branch = $bill->branch;
        $organization = $branch ? $branch->organization : \Tek2991\Accounting\Models\Organization::current();
        $companyName = $organization->name ?? 'Dwelly Property Management';
        $branchName = $branch ? $branch->name : null;
        $branchAddress = $branch ? implode(', ', array_filter([$branch->address, $branch->city, $branch->state?->name, $branch->postal_code])) : '';
    @endphp

    {{-- Header --}}
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px;">
        <tr>
            <td style="vertical-align: top; width: 50%;">
                <div class="logo-title">{{ $companyName }}</div>
                <div class="company-subtitle">Contractor Accounts Payable &bull; Vendor Bill</div>
                @if($branchName)
                    <div style="font-size: 10px; color: #475569; margin-top: 4px; font-weight: 600;">Branch: {{ $branchName }}</div>
                @endif
                @if($branchAddress)
                    <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">{{ $branchAddress }}</div>
                @endif
                @if($organization && $organization->gstin)
                    <div style="font-size: 9.5px; color: #64748b; margin-top: 2px;">GSTIN: {{ $organization->gstin }}</div>
                @endif
            </td>
            <td style="vertical-align: top; width: 50%; text-align: right;">
                <div class="doc-title" style="margin-bottom: 6px;">Vendor Bill</div>
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Bill Number:</td>
                        <td style="text-align: right; font-weight: bold; font-size: 10.5px; padding: 2px 0 2px 8px; width: 140px;">{{ $bill->bill_number }}</td>
                    </tr>
                    @if($bill->vendor_reference)
                        <tr>
                            <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Vendor Reference:</td>
                            <td style="text-align: right; font-weight: bold; font-size: 10.5px; padding: 2px 0 2px 8px;">{{ $bill->vendor_reference }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Issue Date:</td>
                        <td style="text-align: right; font-weight: bold; font-size: 10.5px; padding: 2px 0 2px 8px;">{{ $bill->issue_date ? $bill->issue_date->format('d M Y') : 'N/A' }}</td>
                    </tr>
                    <tr>
                        <td style="text-align: right; color: #64748b; font-size: 10px; padding: 2px 0;">Due Date:</td>
                        <td style="text-align: right; font-weight: bold; font-size: 10.5px; padding: 2px 0 2px 8px;">{{ $bill->due_date ? $bill->due_date->format('d M Y') : 'N/A' }}</td>
                    </tr>
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
    <div style="width: 100%; height: 2px; background: #b45309; margin-bottom: 18px;"></div>

    {{-- Party info grid --}}
    <table class="info-grid">
        <tr>
            <td class="info-box">
                <div class="box-title">Vendor / Contractor (Payee)</div>
                <div style="font-weight: bold; font-size: 11px; color: #0f172a; margin-bottom: 4px;">
                    {{ $bill->contact?->name ?: 'Vendor / Contractor' }}
                </div>
                @if($bill->contact?->billing_address)
                    <div class="info-row">
                        <span class="info-label">Address:</span>
                        <span class="info-value">
                            @if(is_array($bill->contact->billing_address))
                                {{ implode(', ', $bill->contact->billing_address) }}
                            @else
                                {{ $bill->contact->billing_address }}
                            @endif
                        </span>
                    </div>
                @endif
                @if($bill->contact?->phone)
                    <div class="info-row">
                        <span class="info-label">Phone:</span>
                        <span class="info-value">{{ $bill->contact->phone }}</span>
                    </div>
                @endif
                @if($bill->contact?->email)
                    <div class="info-row">
                        <span class="info-label">Email:</span>
                        <span class="info-value">{{ $bill->contact->email }}</span>
                    </div>
                @endif
                @if($bill->contact?->gstin)
                    <div class="info-row">
                        <span class="info-label">GSTIN:</span>
                        <span class="info-value" style="font-weight: bold;">{{ $bill->contact->gstin }}</span>
                    </div>
                @endif
            </td>
            <td style="width: 4%;"></td>
            <td class="info-box">
                <div class="box-title">Billed To (Entity)</div>
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
                        <span class="info-label">Location:</span>
                        <span class="info-value">{{ $branchAddress }}</span>
                    </div>
                @endif
                @if($organization && $organization->gstin)
                    <div class="info-row">
                        <span class="info-label">GSTIN:</span>
                        <span class="info-value">{{ $organization->gstin }}</span>
                    </div>
                @endif
                @if($organization && $organization->pan)
                    <div class="info-row">
                        <span class="info-label">PAN:</span>
                        <span class="info-value">{{ $organization->pan }}</span>
                    </div>
                @endif
            </td>
        </tr>
    </table>

    {{-- Line items table --}}
    <table class="items-table">
        <thead>
            <tr>
                <th style="width: 50%;">Description / Scope of Work</th>
                <th class="text-center" style="width: 12%;">Qty</th>
                <th class="text-right" style="width: 18%;">Unit Rate</th>
                <th class="text-right" style="width: 20%;">Total Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($bill->items as $item)
                <tr>
                    <td>
                        <strong>{{ $item->description ?: 'Contractor Trade Services' }}</strong>
                    </td>
                    <td class="text-center">{{ (float) ($item->quantity ?: 1) }}</td>
                    <td class="text-right">&#8377; {{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="text-right" style="font-weight: bold;">&#8377; {{ number_format((float) ($item->line_total ?: $item->unit_price * ($item->quantity ?: 1)), 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td><strong>{{ $bill->notes ?: 'Maintenance Repair Services' }}</strong></td>
                    <td class="text-center">1</td>
                    <td class="text-right">&#8377; {{ number_format((float) $bill->grand_total, 2) }}</td>
                    <td class="text-right" style="font-weight: bold;">&#8377; {{ number_format((float) $bill->grand_total, 2) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Summary table --}}
    <div class="summary-wrapper">
        <table class="summary-table" align="right">
            <tr>
                <td class="summary-label">Subtotal:</td>
                <td class="summary-val">&#8377; {{ number_format((float) ($bill->subtotal ?: $bill->grand_total), 2) }}</td>
            </tr>
            @if((float) $bill->tax_total > 0)
                <tr>
                    <td class="summary-label">Tax Total:</td>
                    <td class="summary-val">&#8377; {{ number_format((float) $bill->tax_total, 2) }}</td>
                </tr>
            @endif
            @if((float) $bill->discount_amount > 0)
                <tr>
                    <td class="summary-label">Discount:</td>
                    <td class="summary-val">-&#8377; {{ number_format((float) $bill->discount_amount, 2) }}</td>
                </tr>
            @endif
            <tr class="grand-total-row">
                <td style="text-align: right;">Grand Total:</td>
                <td style="text-align: right;">&#8377; {{ number_format((float) $bill->grand_total, 2) }}</td>
            </tr>
            @if((float) $bill->amount_paid > 0)
                <tr>
                    <td class="summary-label">Amount Paid:</td>
                    <td class="summary-val">&#8377; {{ number_format((float) $bill->amount_paid, 2) }}</td>
                </tr>
            @endif
            <tr class="balance-due-row">
                <td style="text-align: right;">Balance Due:</td>
                <td style="text-align: right;">&#8377; {{ number_format((float) ($bill->balance_due ?? $bill->grand_total), 2) }}</td>
            </tr>
        </table>
    </div>

    {{-- Notes / Remarks --}}
    @if($bill->notes)
        <div class="notes-box">
            <div class="notes-title">Notes &amp; Reference:</div>
            <div>{{ $bill->notes }}</div>
        </div>
    @endif

    {{-- Footer --}}
    <table class="footer-table">
        <tr>
            <td style="width: 60%; vertical-align: top;">
                This is a computer-generated vendor bill issued under {{ $companyName }} Accounts Payable.
            </td>
            <td style="width: 40%; text-align: right; vertical-align: top;">
                Generated: {{ now()->format('d M Y, h:i A') }}
            </td>
        </tr>
    </table>
</body>
</html>
