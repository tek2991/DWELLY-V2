<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Leave and License Agreement - {{ $agreement->code ?? 'Draft' }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 13px;
            line-height: 1.5;
            color: #222;
            margin: 20px;
        }
        h1, h2, h3, h4 {
            color: #000;
            margin-top: 10px;
            margin-bottom: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 25px;
        }
        .header h2 {
            font-size: 20px;
            text-decoration: underline;
            text-transform: uppercase;
        }
        .section-title {
            font-weight: bold;
            font-size: 14px;
            margin-top: 15px;
            margin-bottom: 5px;
            text-decoration: underline;
        }
        .form-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            padding: 0 4px;
            font-weight: bold;
        }
        ul, ol {
            margin-top: 5px;
            margin-bottom: 10px;
            padding-left: 20px;
        }
        li {
            margin-bottom: 4px;
        }
        .page-break {
            page-break-after: always;
        }
        .signature-section {
            margin-top: 40px;
            width: 100%;
        }
        .signature-box {
            display: inline-block;
            width: 45%;
            vertical-align: top;
        }
        .signature-line {
            border-bottom: 1px solid #000;
            width: 100%;
            margin-bottom: 5px;
            height: 35px;
        }
        .footer {
            margin-top: 40px;
            text-align: center;
            font-size: 11px;
            color: #666;
            border-top: 1px solid #ddd;
            padding-top: 8px;
        }
        table.audit-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 12px;
        }
        table.audit-table th, table.audit-table td {
            border: 1px solid #999;
            padding: 6px 8px;
            text-align: left;
        }
        table.audit-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="header">
        <h2>LEAVE AND LICENSE AGREEMENT</h2>
    </div>

    <p>
        This Leave and License Agreement (“Agreement”) is made and executed in <strong>Guwahati</strong> on this 
        <span class="form-line">{{ $agreement->created_at ? $agreement->created_at->format('jS \d\a\y \o\f F Y') : date('jS \d\a\y \o\f F Y') }}</span> 
        BETWEEN
    </p>

    <p>
        <strong>{{ $owner->display_name ?? 'Property Owner' }}</strong>, 
        @if(!empty($owner->individual?->parent_name)) S/o or D/o {{ $owner->individual->parent_name }}, @endif
        resident of <span class="form-line">{{ $ownerAddress ?? '_______________________________' }}</span>
        (hereinafter referred to as the <strong>“Licensor”</strong>, which shall mean and include its legal representatives, executors, assignees, employees and administrators)
    </p>

    <p style="text-align: center; font-weight: bold; margin: 15px 0;">AND</p>

    <p>
        <strong>{{ $tenant->display_name ?? 'Tenant Name' }}</strong>, 
        @if(!empty($tenant->individual?->parent_name)) C/o {{ $tenant->individual->parent_name }}, @endif
        resident of <span class="form-line">{{ $tenantAddress ?? '_______________________________' }}</span>
        @if(!empty($tenant->individual?->aadhaar_number)) with Aadhaar number <strong>{{ $tenant->individual->aadhaar_number }}</strong>, @endif
        Phone number: <strong>{{ $tenant->phone ?? '_________________' }}</strong>
        (hereinafter referred to as the <strong>“Licensee”</strong>, which shall mean and include its legal representatives, executors, assignees and administrators).
    </p>

    <p>
        AND WHEREAS the ‘Licensor’ is the absolute owner in full possession of the constructed structure described as 
        <strong>{{ $property->building_name ?? $property->name ?? '' }}, {{ $propertyAddress ?? '_______________________________' }}</strong> 
        (hereinafter referred to as the <strong>“Licensed Premises”</strong>) and is desirous of giving the said premises.
    </p>

    <p>
        AND WHEREAS the Licensee herein is in need of the above mentioned premises for residential use and has approached the Licensor with a request to use and occupy the premises on temporary basis for a period commencing from 
        <span class="form-line">{{ $agreement->start_date ? $agreement->start_date->format('jS F Y') : '________' }}</span> 
        and ending on 
        <span class="form-line">{{ $agreement->end_date ? $agreement->end_date->format('jS F Y') : '________' }}</span> 
        as per the terms and conditions hereafter appearing herein below:
    </p>

    <p>Now therefore it is hereby agreed to, declared and recorded by and between the parties hereto as follows:-</p>

    <div class="section-title">Commencement and Period:</div>
    <ul>
        <li>
            This Agreement shall commence from <strong>{{ $agreement->start_date ? $agreement->start_date->format('jS F Y') : '________' }}</strong> 
            and ending on <strong>{{ $agreement->end_date ? $agreement->end_date->format('jS F Y') : '________' }}</strong>. 
            If the Licensee vacates the leased premises within the first <strong>Six (6) months</strong> from the start of the rental period then the entire security deposit will be charged as cancellation fee. Exceptions to be granted for medical emergency/crisis scenarios/official transfers upon providing supporting documents as proof.
        </li>
        <li>
            The agreement can be renewed after the end of each tenure as per terms mentioned in the ‘renewal’ section of this agreement.
        </li>
    </ul>

    <div class="section-title">Rent & Deposit:</div>
    <ul>
        <li>
            The Licensee shall pay a license fee of <strong>Rupees {{ $rentInWords ?? number_format($agreement->rent_amount ?? 0, 2) }} (INR {{ number_format($agreement->rent_amount ?? 0, 2) }})</strong> per month for the use of the said Licensed premises directly to the Licensor’s authorised representative’s bank account details mentioned in <strong>Annexure - I</strong>, rent collection and the flat is managed by the authorised collection representative <strong>Assam Alay (Dwelly)</strong>.
        </li>
        <li>
            The License amount shall be payable within the <strong>first five days</strong> of the concerned month.
        </li>
        <li>
            In the event of failure of payment after the 15th day of the month the Licensor has every right to cancel the agreement with immediate effect without any notice. In such a situation the entire security amount will be used to compensate for the default in payment and the Licensed premises will be claimed back by the Licensor immediately.
        </li>
        <li>
            The two months security deposit of <strong>Rupees {{ $depositInWords ?? number_format($agreement->security_deposit ?? 0, 2) }} (INR {{ number_format($agreement->security_deposit ?? 0, 2) }})</strong> 
            @if(!empty($agreement->security_deposit_notes)) ({{ $agreement->security_deposit_notes }}) @endif 
            shall be paid to the Licensor before moving-in to the said premises and the same interest free amount shall be returned to the Licensee within 7 to 10 bank working days of vacating the property and the key to the Licensed premise has been handed over to the Licensor.
        </li>
        <li>
            The keys of the property will only be handed over to the Licensee once the entire security deposit has been paid to the bank account mentioned in <strong>Annexure - I</strong>.
        </li>
        <li>
            The security deposit will <strong>NOT BE ADJUSTED</strong> against any month’s payable amount.
        </li>
    </ul>

    <div class="section-title">Maintenance Charge:</div>
    <ul>
        <li>The outgoings towards all Government & Municipal rates and taxes and other levies shall be cleared and paid by the Licensor as applicable.</li>
    </ul>

    <div class="section-title">Electricity & Other charges:</div>
    <ul>
        <li>
            The Licensee herein shall pay the electricity bills timely for the energy consumed as per the tariff bills from the electricity supply agencies. The APDCL prepaid smart meter consumer ID is <strong>{{ $agreement->apdcl_consumer_id ?? '_________________' }}</strong>.
        </li>
        <li>That the Licensee shall ensure that all electricity, water and gas (if any) connections to the said house are maintained in good condition.</li>
        <li>The Licensor shall ensure that all charges for electricity and water consumed in respect of the said apartment/flat prior to the commencement of the agreement hereby granted are paid by the Licensor.</li>
        <li>The society/maintenance fee (currently included in the rent) shall be paid by the Licensee. However, if the society fee increases in the future then the corresponding amount has to be paid by the Licensee.</li>
    </ul>

    <div class="page-break"></div>

    <div class="section-title">Furniture/Fittings & Appliances:</div>
    <ul>
        <li>
            The Licensed premises shall be kept in the best condition possible. If any of the items (as per the items mentioned in <strong>Annexure - III</strong>) along with plumbing fittings, electrical fittings are damaged or lost during the period of the Licensee’s stay due to Licensee’s wilful misconduct or negligence, the cost of the item/s has to be borne by the Licensee on actual Maximum Retail Price of the product (without any depreciation).
        </li>
        <li>
            No tampering with any electrical boards and no pasting of any materials or putting of nails on the walls shall be permitted. Excessive drilling, nailing or installation of multiple fixtures that may damage the walls shall not be permitted and any repair/repainting cost arising out of such damage shall be borne by the Licensee.
        </li>
        <li>
            The Property shall be returned by the Licensee in the same condition as documented in the Move-In Audit photographs, subject only to reasonable wear and tear arising from building seepage or structural leakage.
        </li>
        <li>
            The Licensee shall be responsible for the regular cleaning and upkeep of all appliances provided in the Premises such as (but not limited to) the air-conditioner and kitchen chimney, at intervals of approximately every 3–4 months.
        </li>
        <li>Any issues (non-structural) of the apartment reported after 7 days of Move-In to be borne by Licensee.</li>
    </ul>

    <div class="section-title">Use:</div>
    <ul>
        <li>The Licensee shall use the said premises for residential purpose only and shall not sub-let/sub-rent it to anyone else.</li>
        <li>The Leave and License Agreement shall not be used by the Licensee for any sort of loan application/ credit card application address verification purposes etc.</li>
        <li>If the Tenant Police Verification report of the Licensee submitted by the authorised Service Provider comes out to be negative or rejected by Assam Police Department, the Licensee shall need to vacate the premises immediately within 24 hours.</li>
    </ul>

    <div class="section-title">Possession / Termination & Renewal:</div>
    <ul>
        <li>If the Licensee wants to vacate the premises before the agreement tenure then <strong>one month’s notice period</strong> needs to be issued at the beginning of a month.</li>
        <li>The Licensor has an option to renew this agreement with a <strong>5% increase</strong> in license fee upon mutually agreed terms. Minimum INR 1,000.00 will be charged by the Service provider for renewal paperwork.</li>
        <li>Minimum Rupees Two Thousand only (INR 2,000.00) will be deducted from the security deposit at the time of refund towards cleaning charges.</li>
        <li>Rupees One Thousand and Five Hundred only (INR 1,500.00) will be charged by the Service provider to the Licensee for the paperwork.</li>
    </ul>

    <p style="margin-top: 30px;">IN WITNESS WHEREOF, THE PARTIES TO HAVE HEREUNTO SET AND SUBSCRIBED THEIR RESPECTIVE HANDS ON THE DAY AND THE YEAR FIRST HEREIN ABOVE WRITTEN.</p>

    <div class="signature-section">
        <div class="signature-box" style="float: left;">
            <div class="signature-line"></div>
            <strong>Licensor:</strong> {{ $owner->display_name ?? 'Property Owner' }}
        </div>
        <div class="signature-box" style="float: right;">
            <div class="signature-line"></div>
            <strong>Licensee:</strong> {{ $tenant->display_name ?? 'Tenant' }}
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="signature-section" style="margin-top: 30px;">
        <div class="signature-box" style="float: left;">
            <div class="signature-line"></div>
            <strong>Witness 1:</strong> _______________________
        </div>
        <div class="signature-box" style="float: right;">
            <div class="signature-line"></div>
            <strong>Witness 2:</strong> _______________________
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="page-break"></div>

    <!-- ANNEXURE I -->
    <div class="header">
        <h2>Annexure I – Rent deposit Account Information</h2>
        <h4>RTGS / NEFT / E-Payment Form</h4>
    </div>
    <p>Sub: Authorization to effect payments through RTGS/NEFT/Electronic Payment Platform</p>
    <p>I/We, hereby, request you to effect all rent payments due to the following bank account details.</p>
    <table class="audit-table" style="width: 80%; margin: 20px auto;">
        <tr>
            <th width="40%">Beneficiary Name</th>
            <td>{{ $annexure1BankDetails['beneficiary_name'] ?? 'ASSAM ALAY' }}</td>
        </tr>
        <tr>
            <th>Name of the Bank</th>
            <td>{{ $annexure1BankDetails['bank_name'] ?? 'IndusInd Bank' }}</td>
        </tr>
        <tr>
            <th>Address of the Bank</th>
            <td>{{ $annexure1BankDetails['bank_address'] ?? 'Beltola, Guwahati' }}</td>
        </tr>
        <tr>
            <th>Bank Account No</th>
            <td>{{ $annexure1BankDetails['account_number'] ?? '201025429005' }}</td>
        </tr>
        <tr>
            <th>Account Type</th>
            <td>{{ $annexure1BankDetails['account_type'] ?? 'Current' }}</td>
        </tr>
        <tr>
            <th>IFSC Code</th>
            <td>{{ $annexure1BankDetails['ifsc_code'] ?? 'INDB0000662' }}</td>
        </tr>
    </table>

    <!-- ANNEXURE II -->
    <div class="header" style="margin-top: 40px;">
        <h2>Annexure II – Security Deposit Refund Account Information</h2>
        <h4>RTGS / NEFT / E-Payment Form</h4>
    </div>
    <p>Sub: Authorization to effect payments through RTGS/NEFT/Electronic Payment Platform</p>
    <p>Name of the Licensee: <strong>{{ $tenant->display_name ?? '' }}</strong></p>
    <table class="audit-table" style="width: 80%; margin: 20px auto;">
        <tr>
            <th width="40%">Beneficiary Name</th>
            <td>{{ $tenantBankDetails['account_holder_name'] ?? $tenant->display_name ?? '_______________________' }}</td>
        </tr>
        <tr>
            <th>Name of the Bank</th>
            <td>{{ $tenantBankDetails['bank_name'] ?? '_______________________' }}</td>
        </tr>
        <tr>
            <th>Address / Branch</th>
            <td>{{ $tenantBankDetails['bank_address'] ?? '_______________________' }}</td>
        </tr>
        <tr>
            <th>Bank Account No</th>
            <td>{{ $tenantBankDetails['account_number'] ?? '_______________________' }}</td>
        </tr>
        <tr>
            <th>Account Type</th>
            <td>{{ $tenantBankDetails['account_type'] ?? 'Saving' }}</td>
        </tr>
        <tr>
            <th>IFSC Code</th>
            <td>{{ $tenantBankDetails['ifsc_code'] ?? '_______________________' }}</td>
        </tr>
        <tr>
            <th>PAN Number</th>
            <td>{{ $tenantBankDetails['pan_number'] ?? $tenant->individual?->pan_number ?? '_______________________' }}</td>
        </tr>
    </table>

    <div class="page-break"></div>

    <!-- ANNEXURE III -->
    <div class="header">
        <h2>Annexure III – Furnishing / Electrical / Electronic Items Information</h2>
        <p style="font-size: 12px; color: #555;">(Pulled from Audit Reference: {{ $audit->audit_number ?? 'N/A' }})</p>
    </div>
    <p>Sub: List of furnishing/electrical/electronic items equipped in the licensed premise.</p>

    @if(isset($auditCategories) && count($auditCategories) > 0)
        @foreach($auditCategories as $category)
            <div style="margin-top: 15px;">
                <h4 style="background: #eef2f7; padding: 4px 8px; margin-bottom: 4px; border-left: 4px solid #3b82f6;">
                    {{ $category->name }}
                </h4>
                <table class="audit-table">
                    <thead>
                        <tr>
                            <th width="5%">#</th>
                            <th width="40%">Item Name</th>
                            <th width="15%">Condition</th>
                            <th width="40%">Notes / Specifications</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($category->items as $index => $item)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td><strong>{{ $item->name }}</strong></td>
                                <td>{{ $item->condition->value ?? $item->condition ?? 'Good' }}</td>
                                <td>{{ $item->remarks ?? $item->snapshot_data['notes'] ?? '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align: center; color: #888;">No specific items listed under this category.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endforeach
    @else
        <p style="text-align: center; font-style: italic; margin-top: 30px; color: #777;">
            No audit reference selected or no inventory items recorded.
        </p>
    @endif

    <div class="footer">
        Dwelly (Assam Alay), Registered Office: #61, Basistha Road, Beltola, Guwahati, Assam – 781028 | M: +91-80994 94817
    </div>

</body>
</html>
