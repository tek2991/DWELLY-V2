<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>MOU Addendum - {{ $mou->number }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 5px;
            color: #1a202c;
        }
        .header h3 {
            font-size: 18px;
            margin-top: 0;
            color: #4a5568;
            text-decoration: underline;
        }
        .section-title {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 20px;
            margin-bottom: 10px;
            color: #1a202c;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table td {
            padding: 6px 10px;
            border: 1px solid #cbd5e0;
        }
        .info-label {
            font-weight: bold;
            background-color: #f7fafc;
            width: 35%;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #718096;
        }
        .signature-section {
            margin-top: 60px;
            width: 100%;
        }
        .signature-box {
            display: inline-block;
            width: 45%;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            width: 100%;
            margin-bottom: 5px;
            height: 40px;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Dwelly</h1>
        <h3>MOU Addendum / Agreement Amendment</h3>
        <p style="font-size: 12px; color: #718096;">
            Addendum No: <strong>{{ $mou->number }}</strong> | 
            Date: <strong>{{ $mou->created_at ? $mou->created_at->format('j F Y') : date('j F Y') }}</strong> | 
            Version: <strong>{{ $mou->version ?? 1 }}</strong>
        </p>
    </div>

    <div class="section-title">1. PARTIES & PROPERTY DETAILS</div>
    <p>
        This Addendum amends the terms of the existing Operational & Marketing Services Agreement between 
        <strong>Dwelly (Assam Alay)</strong> (Service Provider) and the Property Owner:
    </p>

    <table class="info-table">
        <tr>
            <td class="info-label">Property Owner:</td>
            <td>{{ $mou->owner_details['name'] ?? $mou->party?->display_name ?? $mou->opportunity?->owner_name ?? '_______________________' }}</td>
        </tr>
        <tr>
            <td class="info-label">Property Address:</td>
            <td>{{ $mou->legal_terms['address'] ?? $mou->property?->address_line_1 ?? $mou->opportunity?->address ?? '_______________________' }}</td>
        </tr>
        <tr>
            <td class="info-label">Amendment Type:</td>
            <td><strong>{{ $mou->type ? $mou->type->label() : 'General Update' }}</strong></td>
        </tr>
    </table>

    <div class="section-title">2. AMENDMENT CLAUSES</div>

    @if($mou->type?->value === 'pricing_update')
        <p>The Parties hereby agree to update the Commercial Pricing Policy applicable to the Property as specified below:</p>
        <table class="info-table">
            <tr>
                <td class="info-label">Financial Model:</td>
                <td><strong>{{ $mou->legal_terms['financial_model_name'] ?? (isset($mou->legal_terms['financial_model_id']) ? \App\Domain\Opportunity\Models\FinancialModel::find($mou->legal_terms['financial_model_id'])?->name : 'N/A') }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">Fee Percentage:</td>
                <td><strong>{{ isset($mou->legal_terms['fee_percentage']) ? rtrim(rtrim(number_format((float)$mou->legal_terms['fee_percentage'], 2), '0'), '.') : '12' }}%</strong></td>
            </tr>
            <tr>
                <td class="info-label">Effective Date:</td>
                <td>{{ $mou->start_date ? $mou->start_date->format('j F Y') : 'Immediate' }}</td>
            </tr>
        </table>
    @elseif($mou->type?->value === 'bank_details_update')
        <p>The Property Owner hereby updates and authorizes the following bank account details for all future rent remittances and financial settlements:</p>
        <table class="info-table">
            <tr>
                <td class="info-label">Beneficiary Name:</td>
                <td><strong>{{ $mou->bank_details['beneficiary_name'] ?? 'N/A' }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">Bank Name:</td>
                <td>{{ $mou->bank_details['bank_name'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="info-label">Account Number:</td>
                <td><strong>{{ $mou->bank_details['account_number'] ?? 'N/A' }}</strong></td>
            </tr>
            <tr>
                <td class="info-label">IFSC Code:</td>
                <td>{{ $mou->bank_details['ifsc_code'] ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td class="info-label">Bank Address:</td>
                <td>{{ $mou->bank_details['bank_address'] ?? 'N/A' }}</td>
            </tr>
        </table>
    @elseif($mou->type?->value === 'sign_authority_update')
        <p>The Signatory Authority representation for the Property Owner is updated as specified below:</p>
        <table class="info-table">
            <tr>
                <td class="info-label">Different Signatory:</td>
                <td><strong>{{ $mou->is_signatory_different ? 'Yes' : 'No' }}</strong></td>
            </tr>
            @if($mou->is_signatory_different)
                <tr>
                    <td class="info-label">Signatory Name:</td>
                    <td><strong>{{ $mou->signatory_details['name'] ?? 'N/A' }}</strong></td>
                </tr>
                <tr>
                    <td class="info-label">Relation to Owner:</td>
                    <td>{{ $mou->signatory_details['relation'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Phone & Email:</td>
                    <td>{{ $mou->signatory_details['phone'] ?? 'N/A' }} | {{ $mou->signatory_details['email'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="info-label">Aadhaar / PAN:</td>
                    <td>{{ $mou->signatory_details['aadhar_number'] ?? 'N/A' }} / {{ $mou->signatory_details['pan_number'] ?? 'N/A' }}</td>
                </tr>
            @endif
        </table>
    @else
        <p>This Addendum records verified administrative updates to the property record as attached in Annexures.</p>
    @endif

    <div class="section-title">3. GENERAL TERMS</div>
    <p>
        All other terms and conditions of the original Agreement remain in full force and effect. 
        In the event of any conflict between the terms of this Addendum and the original Agreement, the terms of this Addendum shall prevail.
    </p>

    <div class="signature-section">
        <div class="signature-box" style="float: left;">
            <div class="signature-line"></div>
            <strong>Service Provider</strong><br>
            <em>Dwelly (Assam Alay)</em>
        </div>
        <div class="signature-box" style="float: right;">
            <div class="signature-line"></div>
            @if($mou->is_signatory_different)
                <strong>{{ $mou->signatory_details['name'] ?? '' }}</strong><br>
                <em>(Signatory for Property Owner / {{ $mou->signatory_details['relation'] ?? '' }})</em>
            @else
                <strong>Property Owner</strong>
            @endif
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="footer">
        Dwelly (Assam Alay), Registered Office: #61, Basistha Road, Beltola, Guwahati, Assam – 781028,<br>
        M: +91-80994 94817, Email: assamalay@gmail.com
    </div>

</body>
</html>
