<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>MOU Document</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            line-height: 1.5;
            color: #333;
        }
        h1, h2, h3, h4 {
            color: #000;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            color: #555;
        }
        .header h3 {
            font-size: 18px;
            text-decoration: underline;
        }
        .section-title {
            font-weight: bold;
            text-decoration: underline;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #777;
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
        .form-line {
            display: inline-block;
            border-bottom: 1px solid #000;
            min-width: 200px;
            padding: 0 5px;
        }
        ul, ol {
            margin-top: 5px;
            margin-bottom: 15px;
            padding-left: 20px;
        }
        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>Dwelly</h1>
        <h3>Marketing & Operational Agency Services Agreement</h3>
        <p style="font-size: 12px; color: #777;">Version: {{ $mou->version ?? 1 }}</p>
    </div>

    <div class="section-title">PARTIES TO AGREEMENT</div>
    <p>
        This agreement for Marketing and Operational agency services is made on the date <br>
        <span class="form-line" style="font-weight: bold;">{{ $mou->created_at->format('j F Y') }}</span> between <strong>Dwelly (Assam Alay)</strong>, 61, Basistha Road, Beltola, Guwahati, Assam – 781028 (herein stated as the <strong>'Service Provider'</strong>) and:
    </p>

    <p>
        <strong>Name:</strong> <span class="form-line">{{ $mou->party->display_name ?? '_______________________' }}</span><br>
        @if($mou->party && $mou->party->party_type === 'individual')
        <strong>S/o or D/o:</strong> <span class="form-line" style="width: 100%;">{{ $mou->party->individual->parent_name ?? '_______________________' }}</span><br>
        @endif
        <strong>Address:</strong> <span class="form-line" style="width: 100%;">{{ $mou->party->addresses->where('is_primary', true)->first()?->address_line_1 ?? $mou->party->addresses->first()?->address_line_1 ?? '_______________________' }}</span><br>
        <strong>Aadhaar No.:</strong> <span class="form-line">{{ $mou->party->individual->aadhaar_number ?? '_______________________' }}</span>
        <strong>PAN No.:</strong> <span class="form-line">{{ $mou->party->individual->pan_number ?? $mou->party->organization->pan ?? '_______________________' }}</span><br>
        <strong>Phone:</strong> <span class="form-line">{{ $mou->party->phone ?? '_______________________' }}</span>
        <strong>Email:</strong> <span class="form-line">{{ $mou->party->email ?? '_______________________' }}</span><br>
        (herein stated as the <strong>'Property Owner'</strong>)
    </p>

    <p>
        The <strong>Property Owner</strong> is the absolute owner in full possession of the constructed structure as described –<br>
        <strong>Property Address:</strong> <span class="form-line" style="width: 100%;">{{ $mou->legal_terms['address'] ?? $mou->opportunity->address ?? '_______________________' }}</span><br>
        <strong>{{ isset($mou->legal_terms['electricity_provider_id']) ? \App\Domain\Property\Models\UtilityProvider::find($mou->legal_terms['electricity_provider_id'])?->name : 'Electricity Provider' }} Connection No.:</strong> <span class="form-line" style="width: 300px;">{{ $mou->legal_terms['electricity_consumer_id'] ?? '_______________________' }}</span><br>
    </p>

    <p>
        By becoming a party to this agreement, the <strong>Property Owner</strong> confirms that all necessary statutory and regulatory permissions, approvals, consents, and permits for the property as per Local, State, and National authorities have been obtained and will remain valid through the duration of this agreement.
    </p>
    <p>
        The Parties expressly agree that the <strong>Service Provider</strong> is not operating as an e-commerce operator or digital platform facilitating supply of services within the meaning of applicable laws. The Service Provider is engaged solely as a property management agent providing marketing, facilitation, and administrative support services on behalf of the Property Owner, as more fully described in the Objectives and Roles & Responsibilities clauses below.
    </p>

    <div class="section-title">OBJECTIVE</div>
    <p>
        The <strong>Property Owner</strong> appoints the <strong>Service Provider</strong> as a non-exclusive agent to provide property marketing, tenant facilitation, rent administration, and limited operational support services strictly on an agency basis during the period of this agreement.
    </p>

    <div class="footer">
        Dwelly (Assam Alay), Registered Office: #61, Basistha Road, Beltola, Guwahati, Assam – 781028,<br>
        M: +91-80994 94817, Email: assamalay@gmail.com
    </div>

    <div class="page-break"></div>

    <div class="header">
        <h1>Dwelly</h1>
    </div>

    <div class="section-title">ROLES AND RESPONSIBILITIES</div>
    <p><strong>A. The Service Provider shall:</strong></p>
    <ol style="list-style-type: lower-alpha;">
        <li>Market and advertise the property across online and offline platforms.</li>
        <li>Identify, screen, and shortlist prospective tenants.</li>
        <li>Coordinate and facilitate execution of the rental agreement between the Owner and Tenant.</li>
        <li>Conduct a property audit (photo/video) before tenant move-in and after move-out, and share the same with the Property Owner.</li>
        <li>Act as a rent collection agent on behalf of the Property Owner (not applicable when the Tenant is a Company, Firm, or Bank).</li>
        <li>Maintain transparent records of all collections and remittances.</li>
        <li>Assist in coordination of tenant-related operational issues.</li>
        <li>Keep the Property Owner informed if the Tenant does not pay rent within 15 days after the due date mentioned in the rental agreement, and provide updates until all dues are cleared.</li>
        <li>Support the eviction process in cases of default, with legal costs borne by the Owner unless otherwise agreed.</li>
        <li>Act in a fiduciary capacity while handling Owner funds.</li>
        <li>Not be responsible for any management, maintenance, or communication related to society matters, unless the issue is created by a Tenant introduced by the Service Provider.</li>
    </ol>

    <p><strong>B. Property Owner's Responsibilities:</strong></p>
    <ol style="list-style-type: lower-alpha;" start="12">
        <li>Retain full ownership and legal control over the property.</li>
        <li>Approve tenant selection and rental terms, and execute the rental agreement directly with the Tenant.</li>
        <li>Ensure the property is legally compliant and adequately maintained.</li>
        <li>Carry out timely repairs for damages caused due to natural wear and tear or any internal building issue. Re-paint the property after every two (2) years or after two (2) tenants have changed, <strong>whichever is later</strong>.</li>
        <li>Carry out timely repairs for damages caused to the furnishing items, electrical and electronic appliances in the property, due to general/natural wear and tear.</li>
        <li>Bear all statutory liabilities including property tax, income tax, and GST (if applicable).</li>
        <li>Handle all management, maintenance, and communication related to society matters.</li>
        <li>Have the right to refuse a potential tenant with proper reasoning and reporting to the Service Provider.</li>
    </ol>

    <div class="footer">
        Dwelly (Assam Alay), Registered Office: #61, Basistha Road, Beltola, Guwahati, Assam – 781028,<br>
        M: +91-80994 94817, Email: assamalay@gmail.com
    </div>

    <div class="page-break"></div>

    <div class="header">
        <h1>Dwelly</h1>
    </div>

    <div class="section-title">COMMERCIALS</div>
    <p><strong>A. Standard Rent Collection Service with monthly fee:</strong></p>
    <p>The <strong>Service Provider</strong> shall charge <strong>12% of the monthly rent as Service Fee</strong> (for an un-furnished property) when the property is occupied. This Service Fee is exclusive of GST (whenever applicable).</p>
    <p>Collection of rent against the property shall be the <strong>Service Provider's</strong> responsibility. Electricity bills, water bills, apartment maintenance charges (wherever applicable), and similar expenses will be paid directly to the Owner.</p>
    <p>The <strong>Security Deposit</strong> collected from the Tenant shall be held with the <strong>Service Provider</strong> and will be refunded directly to the Tenant as per the terms and conditions of the rental agreement signed between the Property Owner and the Tenant.</p>
    <p>In case of continuous rent payment default by the Tenant while retaining possession of the property, the <strong>Service Provider</strong> will cover rental payments for a maximum of <strong>two (2) defaulting months</strong> only.</p>

    <p><strong>B. Rent-Collection Service with Yearly fee:</strong></p>
    <p>The <strong>Service Provider</strong> shall collect rent/license fee/security deposit, and any other applicable fees from the Tenant and remit the same to the Owner's bank account. A Service Fee equivalent to <strong>One (1) month's rental amount shall be charged yearly, exclusive of GST (wherever applicable)</strong>. This Service Fee shall be payable to the <strong>Service Provider</strong> within the first month of the Tenant's move-in and subsequently within the first month of each rental agreement renewal. Unlike the Standard Rent Collection Service, this model is not contingent on the occupancy status of the property. Under this arrangement, the <strong>Service Provider</strong> shall be responsible for all services related to payments, including collection and timely remittance.</p>
    <p>The <strong>Security Deposit</strong> collected from the Tenant shall be held with the <strong>Service Provider</strong> and will be refunded directly to the Tenant as per the terms and conditions of the rental agreement signed between the Property Owner and the Tenant.</p>

    <p><strong>C. Non-Rent-Collection Service:</strong></p>
    <p>Where rent collection services are not availed, and rent/license fee/security deposit, and any other applicable fees are paid directly to the Owner's bank account by the Tenant, a <strong>Service Fee equivalent to One (1) month's rental amount shall be charged yearly to the Property Owner, exclusive of GST (wherever applicable)</strong>. This Service Fee shall be payable to the <strong>Service Provider</strong> within the first month of the Tenant's move-in and subsequently within the first month of each rental agreement renewal. Under this arrangement, the <strong>Service Provider</strong> shall bear no responsibility for any payment-related services, including but not limited to rent guarantee, timely rent collection, or security deposit refunds.</p>

    <div class="footer">
        Dwelly (Assam Alay), Registered Office: #61, Basistha Road, Beltola, Guwahati, Assam – 781028,<br>
        M: +91-80994 94817, Email: assamalay@gmail.com
    </div>

    <div class="page-break"></div>

    <div class="header">
        <h1>Dwelly</h1>
    </div>

    <p>The Owner and the Service Provider each reserve the right to propose a change in the applicable <strong>Commercial model - A, B, or C</strong> - based on the nature and profile of the Tenant. However, any such change shall be mutually agreed upon and approved by both parties in writing prior to the execution of the relevant rental agreement or any modification thereof.<br>
    <strong>The Financial Model chosen by the Owner is <span style="text-decoration: underline; font-weight: bold;">{{ isset($mou->legal_terms['financial_model_id']) ? \App\Domain\Opportunity\Models\FinancialModel::find($mou->legal_terms['financial_model_id'])?->name : ($mou->opportunity->expectedFinancialModel->name ?? '________') }}</span>.</strong></p>

    <div class="section-title">TAXES & RECONCILIATION</div>
    <p>The <strong>Service Provider</strong> does not take responsibility for any default in payment of applicable taxes by the <strong>Property Owner</strong>.</p>
    <p>Reconciliation of accounts will take place between the <strong>5th to 10th of each calendar month</strong>. If the last due date falls on a Sunday or public holiday, reconciliation will be done on the next working day. In case of any disputes, the same must be raised and settled by the <strong>20th day</strong> of that month. Subsequent to resolution, payment will be made by the Service Provider online or by cheque.</p>
    <p>The <strong>Service Provider</strong> is not responsible for any failure to perform its obligations under this agreement if it is prevented or delayed by an event of <strong>force majeure</strong>.</p>
    <p>The reconciliation clause is not applicable if rent/license fee is paid directly to the <strong>Property Owner</strong> by the Tenant.</p>

    <div class="section-title">DURATION & TERMINATION</div>
    <p>This agreement will start on <strong><span style="text-decoration: underline;">{{ $mou->created_at->format('j F Y') }}</span></strong>. The agreement can be terminated with a <strong>60-days written notice</strong> in advance given by either party.</p>

    <div class="section-title">CONFIDENTIALITY</div>
    <p>All terms and conditions of this agreement shall remain confidential during the period of contract. In case of termination, the <strong>Property Owner</strong> will be liable to abide by the terms of confidentiality for <strong>18 months</strong> from the date of termination. Confidentiality obligations extend to existing family members and employees of both parties.</p>

    <div class="section-title">RIGHTS & EXCLUSIVITY</div>
    <p>The <strong>Service Provider</strong> will have no ownership rights over the <strong>Property Owner's</strong> property or furnishing items. The property will continue to be under the Property Owner's ownership or lease agreement.</p>
    <p>The <strong>Service Provider</strong> will be able to advertise the property for rental purposes across online and offline channels using necessary photos and videos of the property.</p>
    <p>The <strong>Property Owner</strong> cannot claim any right, title, or interest in the intellectual property, proprietary material, or information of the <strong>Service Provider</strong>.</p>

    <div class="section-title">DISPUTES</div>
    <p>All disputes between the parties will fall under the legal jurisdiction of the <strong>Gauhati High Court</strong>.</p>
    <p>In case of a leased property, disputes between the <strong>'Property Owner'</strong> (Lessee) and the Lessor will be resolved mutually, without making the Service Provider a party to such disputes either during or after termination of this contract.</p>

    <div class="signature-section">
        <div class="signature-box" style="float: left;">
            <div class="signature-line"></div>
            <strong>Service Provider</strong><br>
            <em>Dwelly (Assam Alay)</em>
        </div>
        <div class="signature-box" style="float: right;">
            <div class="signature-line"></div>
            <strong>Property Owner</strong>
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="footer">
        Dwelly (Assam Alay), Registered Office: #61, Basistha Road, Beltola, Guwahati, Assam – 781028,<br>
        M: +91-80994 94817, Email: assamalay@gmail.com
    </div>

    <div class="page-break"></div>

    <div class="header">
        <h1>Dwelly</h1>
        <h3>Annexure I – Account Information</h3>
        <h3>RTGS / NEFT / E-Payment Mandate Form</h3>
    </div>

    <p>
        <strong>Date:</strong> <span class="form-line">{{ $mou->created_at->format('j F Y') }}</span>
    </p>

    <p><strong>Sub:</strong> Authorization to effect payments through RTGS / NEFT / Electronic Payment Platform</p>

    <p>I/We hereby request you to effect all payments due to us from the Service Provider to my/our bank account, details of which are given below, through RTGS/NEFT/Electronic Payment Platform.</p>

    <p>
        <strong>Name of the Property Owner:</strong> <span class="form-line" style="width: 300px;">{{ $mou->party->display_name ?? '_______________________' }}</span>
    </p>

    <p style="font-weight: bold; font-style: italic; text-decoration: underline;">Bank Details:</p>
    <p>
        <strong>Beneficiary Name:</strong> <span class="form-line" style="width: 300px;">{{ $mou->bank_details['account_holder_name'] ?? '_______________________' }}</span><br>
        <strong>Name of the Bank:</strong> <span class="form-line" style="width: 300px;">{{ $mou->bank_details['bank_name'] ?? '_______________________' }}</span><br>
        <strong>Address of the Bank:</strong> <span class="form-line" style="width: 300px;">{{ $mou->bank_details['bank_address'] ?? '_______________________' }}</span><br>
        <strong>Bank Account No.:</strong> <span class="form-line" style="width: 300px;">{{ $mou->bank_details['account_number'] ?? '_______________________' }}</span><br>
        <strong>IFSC Code:</strong> <span class="form-line" style="width: 300px;">{{ $mou->bank_details['ifsc_code'] ?? '_______________________' }}</span>
    </p>

    <div class="signature-section">
        <div class="signature-box" style="float: left;">
            <div class="signature-line"></div>
            <strong>Service Provider</strong><br>
            <em>Dwelly (Assam Alay)</em>
        </div>
        <div class="signature-box" style="float: right;">
            <div class="signature-line"></div>
            <strong>Property Owner</strong>
        </div>
        <div style="clear: both;"></div>
    </div>

    <div class="footer">
        Dwelly (Assam Alay), Registered Office: #61, Basistha Road, Beltola, Guwahati, Assam – 781028,<br>
        M: +91-80994 94817, Email: assamalay@gmail.com
    </div>

    @if(isset($attachments) && count($attachments) > 0)
        <div class="page-break"></div>
        <div class="header">
            <h1>Dwelly</h1>
            <h3>Annexure II – KYC & Documents</h3>
        </div>
        
        @foreach($attachments as $attachment)
            <div style="margin-bottom: 2rem; text-align: center;">
                <h4>{{ $attachment['name'] }}</h4>
                <img src="{{ $attachment['data'] }}" style="max-width: 100%; max-height: 800px; border: 1px solid #ccc; padding: 5px;" alt="Attachment">
            </div>
            @if(!$loop->last)
                <div class="page-break"></div>
            @endif
        @endforeach
    @endif

</body>
</html>
