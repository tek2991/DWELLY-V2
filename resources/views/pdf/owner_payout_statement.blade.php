<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Owner Monthly Report & Statement - {{ $payout->id }}</title>
    <style>
        @page {
            margin: 24px 30px;
        }
        * {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #1e293b;
            margin: 0;
            padding: 0;
            line-height: 1.4;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            border-bottom: 2px solid #059669;
            padding-bottom: 10px;
        }
        .brand-title {
            font-size: 22px;
            font-weight: bold;
            color: #047857;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .brand-subtitle {
            font-size: 8.5px;
            color: #64748b;
            margin-top: 1px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .agency-notice {
            font-size: 8px;
            color: #047857;
            background: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 4px;
            padding: 3px 6px;
            display: inline-block;
            margin-top: 4px;
        }
        .doc-title {
            font-size: 15px;
            font-weight: bold;
            color: #0f172a;
            text-align: right;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin: 0;
        }
        .doc-sub {
            font-size: 8.5px;
            color: #64748b;
            text-align: right;
            margin-top: 2px;
        }
        .badge {
            display: inline-block;
            padding: 2px 7px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .badge-completed { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef3c7; color: #92400e; }
        .badge-failed { background: #fee2e2; color: #991b1b; }

        /* KPI Banner */
        .kpi-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 6px 0;
            margin-left: -6px;
            margin-right: -6px;
            margin-bottom: 12px;
        }
        .kpi-card {
            border-radius: 6px;
            padding: 8px 10px;
            vertical-align: middle;
            text-align: center;
        }
        .kpi-label {
            font-size: 7.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }
        .kpi-value {
            font-size: 12.5px;
            font-weight: bold;
        }

        /* 3-Column Info Cards */
        .info-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-left: -8px;
            margin-right: -8px;
            margin-bottom: 12px;
        }
        .info-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 8px 10px;
            vertical-align: top;
        }
        .card-header {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #059669;
            margin-bottom: 5px;
            border-bottom: 1px dashed #cbd5e1;
            padding-bottom: 3px;
        }
        .card-row {
            margin-bottom: 2.5px;
            font-size: 8.5px;
        }
        .card-label {
            color: #64748b;
            display: inline-block;
            width: 75px;
        }
        .card-value {
            font-weight: 600;
            color: #0f172a;
        }

        /* Tables */
        .section-title {
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin-top: 10px;
            margin-bottom: 4px;
            border-left: 3px solid #059669;
            padding-left: 6px;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .data-table th {
            background: #059669;
            color: #ffffff;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 5px 8px;
            text-align: left;
        }
        .data-table td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 9px;
        }
        .data-table tr:nth-child(even) td {
            background: #fafafa;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Summary Bottom Table */
        .summary-wrapper {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            margin-bottom: 10px;
        }
        .instructions-cell {
            width: 55%;
            vertical-align: top;
            padding-right: 10px;
        }
        .totals-cell {
            width: 45%;
            vertical-align: top;
        }

        .instructions-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            padding: 8px 10px;
        }
        .instructions-title {
            font-size: 8.5px;
            font-weight: bold;
            text-transform: uppercase;
            color: #166534;
            margin-bottom: 5px;
            border-bottom: 1px dashed #86efac;
            padding-bottom: 2px;
        }
        .inst-row {
            font-size: 8px;
            margin-bottom: 2.5px;
            color: #14532d;
        }
        .inst-label {
            display: inline-block;
            width: 95px;
            color: #166534;
            font-weight: 500;
        }

        .totals-table {
            width: 100%;
            border-collapse: collapse;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
        }
        .totals-table td {
            padding: 5px 8px;
            font-size: 8.5px;
        }
        .totals-table .grand-total-row td {
            background: #059669;
            color: #ffffff;
            font-weight: bold;
            font-size: 10.5px;
            padding: 6px 8px;
        }

        .legal-notice {
            margin-top: 8px;
            padding: 6px 8px;
            background: #f8fafc;
            border-left: 3px solid #059669;
            border-radius: 0 4px 4px 0;
            font-size: 7.5px;
            color: #64748b;
            line-height: 1.35;
        }

        .footer {
            margin-top: 8px;
            text-align: center;
            font-size: 7.5px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 6px;
        }
    </style>
</head>
<body>
    @php
        $owner = $statementData['owner'] ?? null;
        $property = $statementData['property'] ?? null;
        $agreement = $statementData['agreement'] ?? null;
        $tenant = $statementData['tenant'] ?? null;
        $commissionInvoice = $statementData['commission_invoice'] ?? null;
        $primaryBank = $statementData['primary_bank'] ?? null;
        $maintenanceDeductions = $statementData['maintenance_deductions'] ?? [];
        $maintenanceActivity = $statementData['maintenance_activity'] ?? [];

        $grossRent = (float) $payout->rent_collected;
        $mgmtFee = (float) $payout->management_fee;
        $advOffset = (float) $payout->advance_offset;
        $reserveDeduction = (float) $payout->reserve_deduction;
        $totalCharges = (float) ($statementData['total_charges'] ?? ($mgmtFee + $advOffset + $reserveDeduction));
        $netPayout = (float) $payout->amount;

        $statusStr = (string) $payout->status;
        $badgeClass = match ($statusStr) {
            'completed' => 'badge-completed',
            'failed' => 'badge-failed',
            default => 'badge-pending',
        };
        $statusLabel = match ($statusStr) {
            'completed' => 'Disbursed / Settled',
            'failed' => 'Disbursement Failed',
            default => 'Pending Settlement',
        };
    @endphp

    <!-- Header Section -->
    <table class="header-table">
        <tr>
            <td style="width: 55%; vertical-align: top;">
                <h1 class="brand-title">DWELLY</h1>
                <div class="brand-subtitle">Property Management & Residential Operations</div>
                <div class="agency-notice">
                    <strong>Managing Agent Property Performance Report & Financial Statement</strong>
                </div>
            </td>
            <td style="width: 45%; vertical-align: top; text-align: right;">
                <div class="doc-title">Owner Monthly Report</div>
                <div class="doc-sub">Property Operational & Remittance Statement</div>
                <div style="margin-top: 4px;">
                    <span class="badge {{ $badgeClass }}">{{ $statusLabel }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Top 4 KPI Metrics Cards -->
    <table class="kpi-table">
        <tr>
            <td class="kpi-card" style="width: 25%; background: #eff6ff; border: 1px solid #bfdbfe;">
                <div class="kpi-label" style="color: #1e40af;">Gross Rent Inflow</div>
                <div class="kpi-value" style="color: #1d4ed8;">₹{{ number_format($grossRent, 2) }}</div>
            </td>
            <td class="kpi-card" style="width: 25%; background: #fef2f2; border: 1px solid #fecaca;">
                <div class="kpi-label" style="color: #991b1b;">Total Charges / Invoice</div>
                <div class="kpi-value" style="color: #b91c1c;">-₹{{ number_format($totalCharges, 2) }}</div>
            </td>
            <td class="kpi-card" style="width: 25%; background: #ecfdf5; border: 1px solid #a7f3d0;">
                <div class="kpi-label" style="color: #065f46;">Net Disbursed to Owner</div>
                <div class="kpi-value" style="color: #047857;">₹{{ number_format($netPayout, 2) }}</div>
            </td>
            <td class="kpi-card" style="width: 25%; background: #f8fafc; border: 1px solid #e2e8f0;">
                <div class="kpi-label" style="color: #475569;">Tenancy Occupancy</div>
                <div class="kpi-value" style="color: #0f172a; font-size: 11px;">
                    {{ $agreement ? 'Occupied (Active)' : 'Vacant' }}
                </div>
            </td>
        </tr>
    </table>

    <!-- 3-Column Info Cards -->
    <table class="info-table">
        <tr>
            <!-- Property & Lease -->
            <td style="width: 33.3%;" class="info-card">
                <div class="card-header">Property & Lease Details</div>
                <div class="card-row">
                    <span class="card-label">Property:</span>
                    <span class="card-value">{{ $property?->building_name ?? $property?->name ?? 'Property' }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Property Code:</span>
                    <span class="card-value">{{ $property?->code ?? '—' }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Active Tenant:</span>
                    <span class="card-value">{{ $tenant?->display_name ?? 'Active Tenant' }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Agreement Ref:</span>
                    <span class="card-value">{{ $agreement?->code ?? 'AGR-TENANCY' }}</span>
                </div>
            </td>

            <!-- Property Owner -->
            <td style="width: 33.3%;" class="info-card">
                <div class="card-header">Property Owner Profile</div>
                <div class="card-row">
                    <span class="card-label">Owner Name:</span>
                    <span class="card-value">{{ $owner?->display_name ?? 'Property Owner' }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Contact:</span>
                    <span class="card-value">{{ $owner?->phone ?? '—' }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Email:</span>
                    <span class="card-value">{{ $owner?->email ?? '—' }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">PAN Number:</span>
                    <span class="card-value">{{ $owner?->individual?->pan_number ?? '—' }}</span>
                </div>
            </td>

            <!-- Payout & Statement Context -->
            <td style="width: 33.3%;" class="info-card">
                <div class="card-header">Statement & Invoicing</div>
                <div class="card-row">
                    <span class="card-label">Billing Cycle:</span>
                    <span class="card-value" style="color: #047857;">{{ $payout->period_formatted ?? ($payout->period_start ? $payout->period_start->format('M Y') : 'Monthly') }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Payout Ref:</span>
                    <span class="card-value" style="font-family: monospace; font-size: 8px;">{{ $payout->transaction?->reference ?? ("PAYOUT-" . substr($payout->id, -8)) }}</span>
                </div>
                <div class="card-row">
                    <span class="card-label">Charges Invoice:</span>
                    <span class="card-value" style="color: #1d4ed8; font-weight: bold;">
                        #{{ $commissionInvoice?->invoice_number ?? 'INV-PENDING' }}
                    </span>
                </div>
                <div class="card-row">
                    <span class="card-label">Settled On:</span>
                    <span class="card-value">{{ $payout->processed_at ? $payout->processed_at->format('d M Y') : now()->format('d M Y') }}</span>
                </div>
            </td>
        </tr>
    </table>

    <!-- Comprehensive Financial Statement Breakdown -->
    <div class="section-title">1. Monthly Financial Account Statement</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 50%;">Description & Revenue / Charge Item</th>
                <th style="width: 25%; text-align: center;">Accounting / Invoice Reference</th>
                <th style="width: 20%; text-align: right;">Amount (₹)</th>
            </tr>
        </thead>
        <tbody>
            <!-- Gross Rent Inflow -->
            <tr>
                <td class="text-center">1</td>
                <td>
                    <strong>Gross Monthly Rent Collection</strong>
                    <div style="font-size: 8px; color: #64748b;">
                        Rental proceeds collected from tenant for {{ $payout->period_formatted }} ({{ $property?->building_name }})
                    </div>
                </td>
                <td class="text-center">
                    <span style="font-size: 8px; color: #166534; font-weight: bold; background: #dcfce7; padding: 1px 6px; border-radius: 3px;">
                        Gross Rental Inflow
                    </span>
                </td>
                <td class="text-right" style="font-weight: 700; color: #0f172a;">
                    + ₹{{ number_format($grossRent, 2) }}
                </td>
            </tr>

            <!-- Management Fee -->
            @if($mgmtFee > 0)
            <tr style="background: #f0fdf4;">
                <td class="text-center">2</td>
                <td>
                    <strong style="color: #047857;">Less: Dwelly Property Management Fee</strong>
                    <div style="font-size: 8px; color: #15803d;">
                        Standard management commission billed under Owner Charges Invoice <strong>#{{ $commissionInvoice?->invoice_number ?? 'AUTO-INV' }}</strong>
                    </div>
                </td>
                <td class="text-center">
                    <span style="font-size: 8px; color: #047857; font-weight: 600;">
                        Tax Invoice #{{ $commissionInvoice?->invoice_number ?? 'INV' }}
                    </span>
                </td>
                <td class="text-right" style="font-weight: 700; color: #dc2626;">
                    - ₹{{ number_format($mgmtFee, 2) }}
                </td>
            </tr>
            @endif

            <!-- Maintenance Deductions -->
            @if($advOffset > 0)
                @if(!empty($maintenanceDeductions))
                    @foreach($maintenanceDeductions as $idx => $mDeduct)
                    <tr>
                        <td class="text-center">{{ 3 + $idx }}</td>
                        <td>
                            <strong>Less: {{ $mDeduct['description'] }}</strong>
                            <div style="font-size: 8px; color: #64748b;">
                                Settlement of maintenance invoice #{{ $mDeduct['invoice_number'] }}
                            </div>
                        </td>
                        <td class="text-center">
                            <span style="font-size: 8px; color: #d97706; font-weight: 600;">Maintenance Offset</span>
                        </td>
                        <td class="text-right" style="font-weight: 700; color: #dc2626;">
                            - ₹{{ number_format($mDeduct['amount'], 2) }}
                        </td>
                    </tr>
                    @endforeach
                @else
                    <tr>
                        <td class="text-center">3</td>
                        <td>
                            <strong>Less: Maintenance & Appliance Advance Offset</strong>
                            <div style="font-size: 8px; color: #64748b;">
                                Recovery of maintenance expenses / appliance advances paid on owner's behalf
                            </div>
                        </td>
                        <td class="text-center">
                            <span style="font-size: 8px; color: #d97706; font-weight: 600;">Advance Offset</span>
                        </td>
                        <td class="text-right" style="font-weight: 700; color: #dc2626;">
                            - ₹{{ number_format($advOffset, 2) }}
                        </td>
                    </tr>
                @endif
            @endif

            <!-- Reserve Retention -->
            @if($reserveDeduction > 0)
            <tr>
                <td class="text-center">4</td>
                <td>
                    <strong>Less: Maintenance Sinking Fund Retention</strong>
                    <div style="font-size: 8px; color: #64748b;">
                        Retained in emergency repair escrow / maintenance reserve
                    </div>
                </td>
                <td class="text-center">
                    <span style="font-size: 8px; color: #4b5563; font-weight: 600;">Reserve Escrow</span>
                </td>
                <td class="text-right" style="font-weight: 700; color: #dc2626;">
                    - ₹{{ number_format($reserveDeduction, 2) }}
                </td>
            </tr>
            @endif
        </tbody>
    </table>

    <!-- Maintenance Activity Section (if any) -->
    @if(!empty($maintenanceActivity))
    <div class="section-title">2. Property Maintenance & Repairs Activity Log</div>
    <table class="data-table" style="margin-bottom: 6px;">
        <thead>
            <tr>
                <th style="width: 15%;">Ticket #</th>
                <th style="width: 45%;">Work Description</th>
                <th style="width: 20%; text-align: center;">Category & Status</th>
                <th style="width: 20%; text-align: right;">Total Cost (₹)</th>
            </tr>
        </thead>
        <tbody>
            @foreach($maintenanceActivity as $mActivity)
            <tr>
                <td style="font-family: monospace; font-weight: bold; color: #0f172a;">{{ $mActivity['ticket_number'] }}</td>
                <td>
                    <strong>{{ $mActivity['title'] }}</strong>
                    <div style="font-size: 7.5px; color: #64748b;">Logged: {{ $mActivity['created_at'] }}</div>
                </td>
                <td class="text-center">
                    <span style="font-size: 7.5px; color: #0284c7; background: #e0f2fe; padding: 1px 4px; border-radius: 2px;">
                        {{ $mActivity['category'] }} • {{ $mActivity['status'] }}
                    </span>
                </td>
                <td class="text-right" style="font-weight: 600;">
                    ₹{{ number_format($mActivity['cost'], 2) }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endif

    <!-- Summary & Bank Remittance Advice -->
    <div class="section-title" style="margin-top: 6px;">{{ !empty($maintenanceActivity) ? '3.' : '2.' }} Remittance & Settlement Summary</div>
    <table class="summary-wrapper">
        <tr>
            <!-- Left: Remittance Bank Account -->
            <td class="instructions-cell">
                <div class="instructions-box">
                    <div class="instructions-title">Electronic Remittance Advice</div>
                    <div class="inst-row">
                        <span class="inst-label">Beneficiary Bank:</span>
                        <strong>{{ $primaryBank?->bank_name ?? 'Owner Bank on File' }}</strong>
                    </div>
                    <div class="inst-row">
                        <span class="inst-label">Account Number:</span>
                        <strong>{{ $primaryBank ? ('•••• •••• •••• ' . substr($primaryBank->account_number ?? '0000', -4)) : 'Linked Bank Account' }}</strong>
                    </div>
                    <div class="inst-row">
                        <span class="inst-label">IFSC Code:</span>
                        <strong>{{ $primaryBank?->ifsc_code ?? '—' }}</strong>
                    </div>
                    <div class="inst-row">
                        <span class="inst-label">Transfer UTR / Ref:</span>
                        <strong style="color: #047857; font-family: monospace;">{{ $payout->transaction?->reference ?? ("PAYOUT-" . substr($payout->id, -8)) }}</strong>
                    </div>
                    <div style="font-size: 7.5px; color: #166534; margin-top: 4px; border-top: 1px dashed #bbf7d0; padding-top: 3px;">
                        * Funds have been electronically disbursed to your bank account via NEFT / RTGS / IMPS.
                    </div>
                </div>
            </td>

            <!-- Right: Financial Statement Totals -->
            <td class="totals-cell">
                <table class="totals-table">
                    <tr>
                        <td style="color: #64748b;">Gross Rent Inflow:</td>
                        <td class="text-right" style="font-weight: 600;">₹{{ number_format($grossRent, 2) }}</td>
                    </tr>
                    <tr>
                        <td style="color: #dc2626;">Total Chargeables Invoiced:</td>
                        <td class="text-right" style="font-weight: 600; color: #dc2626;">- ₹{{ number_format($totalCharges, 2) }}</td>
                    </tr>
                    <tr class="grand-total-row">
                        <td>NET DISBURSED TO OWNER:</td>
                        <td class="text-right">₹{{ number_format($netPayout, 2) }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Legal & Tax Compliance Note -->
    <div class="legal-notice">
        <strong>Report & Invoicing Relationship Disclosure:</strong> This document represents the complete monthly Property Performance & Remittance Report. All service charges, management fees, and maintenance offsets are formally billed under official B2B Tax Invoice <strong>#{{ $commissionInvoice?->invoice_number ?? 'INV' }}</strong> (accessible under Owner Charges Invoice). The invoice has been settled in full at source via deduction from the gross rental proceeds.
    </div>

    <div class="footer">
        Dwelly Property Management • Financial Services Division • Support: finance@dwelly.in • Report Ref: #{{ $payout->id }}
        @if(!empty($payout->pdf_checksum))
            • SHA-256 Verified: <span style="font-family: monospace;">{{ substr($payout->pdf_checksum, 0, 16) }}...</span>
        @endif
    </div>
</body>
</html>
