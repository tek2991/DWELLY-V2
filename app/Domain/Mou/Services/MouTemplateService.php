<?php

namespace App\Domain\Mou\Services;

use App\Domain\Mou\Enums\MouType;
use App\Domain\Mou\Models\Mou;
use App\Domain\Opportunity\Models\FinancialModel;
use App\Domain\Property\Models\UtilityProvider;

class MouTemplateService
{
    /**
     * Resolves all locked parameters from the MOU model and its relationships.
     */
    public function getResolvedParameters(Mou $mou): array
    {
        $mou->loadMissing(['property', 'party', 'opportunity']);

        // 1. Owner Details
        $ownerName = $mou->owner_details['name']
            ?? $mou->party?->display_name
            ?? $mou->opportunity?->owner_name
            ?? '_______________________';

        $ownerParentName = $mou->owner_details['parent_name']
            ?? $mou->party?->individual?->parent_name
            ?? '';

        $ownerAddress = $mou->owner_details['address']
            ?? $mou->party?->addresses?->where('is_primary', true)->first()?->address_line_1
            ?? $mou->party?->addresses?->first()?->address_line_1
            ?? $mou->opportunity?->address
            ?? '_______________________';

        $ownerAadhaar = $mou->getResolvedAadhaarNumber()
            ?? $mou->owner_details['aadhar_number']
            ?? $mou->party?->individual?->aadhaar_number
            ?? '_______________________';

        $ownerPan = $mou->getResolvedPanNumber()
            ?? $mou->owner_details['pan_number']
            ?? $mou->party?->individual?->pan_number
            ?? $mou->party?->organization?->pan
            ?? '_______________________';

        $ownerPhone = $mou->owner_details['phone']
            ?? $mou->party?->phone
            ?? $mou->opportunity?->owner_phone
            ?? '_______________________';

        $ownerEmail = $mou->owner_details['email']
            ?? $mou->party?->email
            ?? $mou->opportunity?->owner_email
            ?? '_______________________';

        // 2. Signatory Details
        $signatoryName = $mou->signatory_details['name'] ?? '_______________________';
        $signatoryRelation = $mou->signatory_details['relation'] ?? '_______________________';
        $signatoryAadhaar = $mou->signatory_details['aadhar_number'] ?? '_______________________';
        $signatoryPan = $mou->signatory_details['pan_number'] ?? '_______________________';

        // 3. Property & Utility Terms
        $propertyAddress = $mou->legal_terms['address']
            ?? $mou->property?->address_line_1
            ?? $mou->opportunity?->address
            ?? '_______________________';

        $electricityProviderName = $mou->legal_terms['electricity_provider_name'] ?? null;
        if (empty($electricityProviderName) && ! empty($mou->legal_terms['electricity_provider_id'])) {
            $electricityProviderName = UtilityProvider::find($mou->legal_terms['electricity_provider_id'])?->name;
        }
        $electricityProviderName = $electricityProviderName ?: 'Electricity Provider';

        $electricityConsumerId = $mou->legal_terms['electricity_consumer_id']
            ?? $mou->kyc_documents['owner']['electricity_bill']['number']
            ?? '_______________________';

        // 4. Commercials & Financial Model
        $feePercentage = isset($mou->legal_terms['fee_percentage'])
            ? rtrim(rtrim(number_format((float) $mou->legal_terms['fee_percentage'], 2), '0'), '.')
            : '12';

        $financialModelName = $mou->legal_terms['financial_model_name'] ?? null;
        if (empty($financialModelName) && ! empty($mou->legal_terms['financial_model_id'])) {
            $financialModelName = FinancialModel::find($mou->legal_terms['financial_model_id'])?->name;
        }
        if (empty($financialModelName) && $mou->opportunity?->expectedFinancialModel) {
            $financialModelName = $mou->opportunity->expectedFinancialModel->name;
        }
        $financialModelName = $financialModelName ?: 'Standard Financial Model';

        // 5. Dates
        $startDate = $mou->start_date
            ? $mou->start_date->format('j F Y')
            : ($mou->created_at ? $mou->created_at->format('j F Y') : date('j F Y'));

        // 6. Bank Details
        $bankBeneficiaryName = $mou->bank_details['beneficiary_name']
            ?? $mou->bank_details['account_holder_name']
            ?? $ownerName;
        $bankName = $mou->bank_details['bank_name'] ?? '_______________________';
        $bankAddress = $mou->bank_details['bank_address'] ?? '_______________________';
        $bankAccountNumber = $mou->bank_details['account_number'] ?? '_______________________';
        $bankAccountType = $mou->bank_details['account_type'] ?? 'Current';
        $bankIfscCode = $mou->bank_details['ifsc_code'] ?? '_______________________';

        return [
            'owner_name' => $ownerName,
            'owner_parent_name' => $ownerParentName,
            'owner_address' => $ownerAddress,
            'owner_aadhaar' => $ownerAadhaar,
            'owner_pan' => $ownerPan,
            'owner_phone' => $ownerPhone,
            'owner_email' => $ownerEmail,
            'is_signatory_different' => (bool) $mou->is_signatory_different,
            'signatory_name' => $signatoryName,
            'signatory_relation' => $signatoryRelation,
            'signatory_aadhaar' => $signatoryAadhaar,
            'signatory_pan' => $signatoryPan,
            'property_address' => $propertyAddress,
            'electricity_provider_name' => $electricityProviderName,
            'electricity_consumer_id' => $electricityConsumerId,
            'fee_percentage' => $feePercentage,
            'financial_model_name' => $financialModelName,
            'start_date' => $startDate,
            'bank_beneficiary_name' => $bankBeneficiaryName,
            'bank_name' => $bankName,
            'bank_address' => $bankAddress,
            'bank_account_number' => $bankAccountNumber,
            'bank_account_type' => $bankAccountType,
            'bank_ifsc_code' => $bankIfscCode,
            'mou_number' => $mou->number ?? 'DRAFT',
            'mou_version' => (string) ($mou->version ?? 1),
        ];
    }

    /**
     * Renders a locked badge HTML snippet for the rich text editor.
     */
    public function renderLockedBadge(string $key, string $label, string $value): string
    {
        $escapedKey = e($key);
        $escapedLabel = e($label);
        $escapedValue = e($value);

        return '<span contenteditable="false" data-type="mergeTag" data-id="'.$escapedKey.'" data-param="'.$escapedKey.'" class="mou-locked-param" '.
            'style="display: inline-block; vertical-align: -2px; white-space: nowrap; padding: 2px 7px; background-color: #f0f9ff; '.
            'border: 1px solid #bae6fd; border-radius: 6px; font-weight: 500; color: #0369a1; '.
            'user-select: all; font-size: 13px; line-height: 1.4; margin: 0 2px; box-shadow: 0 1px 2px rgba(0,0,0,0.04);">'.
            '🔒 '.$escapedLabel.': '.$escapedValue.
            '</span>';
    }

    /**
     * Returns an array of merge tag descriptors for Filament's RichEditor toolbar.
     * When an MOU record is supplied, the actual verified value is displayed in the label.
     */
    public function getMergeTags(?Mou $mou = null): array
    {
        $params = $mou ? $this->getResolvedParameters($mou) : [];

        $format = function (string $key, string $label) use ($params) {
            $val = $params[$key] ?? null;
            if (filled($val)) {
                return '🔒 '.$label.': '.$val;
            }

            return '🔒 '.$label.': (Not provided)';
        };

        return [
            'owner_name' => $format('owner_name', 'Property Owner Name'),
            'owner_parent_name' => $format('owner_parent_name', 'Owner Parent/Spouse Name'),
            'owner_address' => $format('owner_address', 'Owner Registered Address'),
            'owner_aadhaar' => $format('owner_aadhaar', 'Owner Aadhaar Number'),
            'owner_pan' => $format('owner_pan', 'Owner PAN Number'),
            'owner_phone' => $format('owner_phone', 'Owner Phone Number'),
            'owner_email' => $format('owner_email', 'Owner Email Address'),
            'signatory_name' => $format('signatory_name', 'Signatory Name'),
            'signatory_relation' => $format('signatory_relation', 'Signatory Relation'),
            'signatory_aadhaar' => $format('signatory_aadhaar', 'Signatory Aadhaar Number'),
            'signatory_pan' => $format('signatory_pan', 'Signatory PAN Number'),
            'property_address' => $format('property_address', 'Property Address'),
            'electricity_provider_name' => $format('electricity_provider_name', 'Electricity Provider'),
            'electricity_consumer_id' => $format('electricity_consumer_id', 'Electricity Consumer ID'),
            'fee_percentage' => $format('fee_percentage', 'Dwelly Fee %'),
            'financial_model_name' => $format('financial_model_name', 'Financial Model'),
            'start_date' => $format('start_date', 'Agreement Start Date'),
            'bank_beneficiary_name' => $format('bank_beneficiary_name', 'Beneficiary Account Name'),
            'bank_name' => $format('bank_name', 'Bank Name'),
            'bank_address' => $format('bank_address', 'Bank Branch Address'),
            'bank_account_number' => $format('bank_account_number', 'Bank Account Number'),
            'bank_account_type' => $format('bank_account_type', 'Bank Account Type'),
            'bank_ifsc_code' => $format('bank_ifsc_code', 'Bank IFSC Code'),
            'mou_number' => $format('mou_number', 'MOU Number'),
            'mou_version' => $format('mou_version', 'Version'),
        ];
    }

    /**
     * Generates default HTML agreement content pre-filled with locked parameters.
     */
    public function getDefaultContent(Mou $mou, bool $forEditor = true): string
    {
        $params = $this->getResolvedParameters($mou);

        $badge = function (string $key, string $label) use ($params, $forEditor) {
            $val = $params[$key] ?? '';
            if (! $forEditor) {
                return '<span class="form-line" style="font-weight: bold;">'.e($val).'</span>';
            }

            return $this->renderLockedBadge($key, $label, $val);
        };

        if ($mou->type && $mou->type !== MouType::ONBOARDING) {
            return $this->getDefaultAddendumContent($mou, $badge, $params);
        }

        return $this->getDefaultStandardMouContent($mou, $badge, $params);
    }

    protected function getDefaultStandardMouContent(Mou $mou, \Closure $badge, array $params): string
    {
        $html = '';

        // Header (centered by default)
        $html .= '<h1 style="text-align: center; font-size: 26px; margin: 0 0 6px 0; color: #1e293b;">Dwelly</h1>';
        $html .= '<h3 style="text-align: center; font-size: 17px; margin: 0 0 6px 0; text-decoration: underline; color: #334155;">Marketing &amp; Operational Agency Services Agreement</h3>';
        $html .= '<p style="text-align: center; font-size: 12px; color: #64748b; margin-top: 4px; margin-bottom: 25px;">Agreement Ref: '.$badge('mou_number', 'MOU Number').' | Version: '.$badge('mou_version', 'Version').'</p>';

        // Section: Parties
        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">PARTIES TO AGREEMENT</h3>';
        $html .= '<p>This agreement for Marketing and Operational agency services is made on the date '.$badge('start_date', 'Date').' between <strong>Dwelly (Assam Alay)</strong>, 61, Basistha Road, Beltola, Guwahati, Assam – 781028 (herein stated as the <strong>\'Service Provider\'</strong>) and:</p>';

        $html .= '<p>';
        $html .= '<strong>Name:</strong> '.$badge('owner_name', 'Name').'<br>';
        if (! empty($params['owner_parent_name'])) {
            $html .= '<strong>S/o or D/o:</strong> '.$badge('owner_parent_name', 'Parent Name').'<br>';
        }
        $html .= '<strong>Address:</strong> '.$badge('owner_address', 'Address').'<br>';
        $html .= '<strong>Aadhaar No.:</strong> '.$badge('owner_aadhaar', 'Aadhaar').' &nbsp;&nbsp;|&nbsp;&nbsp; <strong>PAN No.:</strong> '.$badge('owner_pan', 'PAN').'<br>';
        $html .= '<strong>Phone:</strong> '.$badge('owner_phone', 'Phone').' &nbsp;&nbsp;|&nbsp;&nbsp; <strong>Email:</strong> '.$badge('owner_email', 'Email').'<br>';
        $html .= '(herein stated as the <strong>\'Property Owner\'</strong>)';
        $html .= '</p>';

        if (! empty($params['is_signatory_different'])) {
            $html .= '<p style="background: #f8fafc; padding: 10px 14px; border-radius: 6px; border: 1px solid #e2e8f0;">';
            $html .= '<strong>Represented by Authorized Signatory:</strong><br>';
            $html .= '<strong>Signatory Name:</strong> '.$badge('signatory_name', 'Signatory Name').'<br>';
            $html .= '<strong>Relation to Owner:</strong> '.$badge('signatory_relation', 'Relation').'<br>';
            $html .= '<strong>Aadhaar No.:</strong> '.$badge('signatory_aadhaar', 'Signatory Aadhaar').' &nbsp;&nbsp;|&nbsp;&nbsp; <strong>PAN No.:</strong> '.$badge('signatory_pan', 'Signatory PAN');
            $html .= '</p>';
        }

        $html .= '<p>';
        $html .= 'The <strong>Property Owner</strong> is the absolute owner in full possession of the constructed structure as described –<br>';
        $html .= '<strong>Property Address:</strong> '.$badge('property_address', 'Property Address').'<br>';
        $html .= '<strong>'.$badge('electricity_provider_name', 'Provider').' Connection No.:</strong> '.$badge('electricity_consumer_id', 'Connection ID').'<br>';
        $html .= '</p>';

        $html .= '<p>By becoming a party to this agreement, the <strong>Property Owner</strong> confirms that all necessary statutory and regulatory permissions, approvals, consents, and permits for the property as per Local, State, and National authorities have been obtained and will remain valid through the duration of this agreement.</p>';
        $html .= '<p>The Parties expressly agree that the <strong>Service Provider</strong> is not operating as an e-commerce operator or digital platform facilitating supply of services within the meaning of applicable laws. The Service Provider is engaged solely as a property management agent providing marketing, facilitation, and administrative support services on behalf of the Property Owner, as more fully described in the Objectives and Roles &amp; Responsibilities clauses below.</p>';

        // Section: Objective
        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">OBJECTIVE</h3>';
        $html .= '<p>The <strong>Property Owner</strong> appoints the <strong>Service Provider</strong> as a non-exclusive agent to provide property marketing, tenant facilitation, rent administration, and limited operational support services strictly on an agency basis during the period of this agreement.</p>';

        // Section: Roles & Responsibilities
        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">ROLES AND RESPONSIBILITIES</h3>';
        $html .= '<p><strong>A. The Service Provider shall:</strong></p>';
        $html .= '<ol style="padding-left: 20px;">';
        $html .= '<li>Market and advertise the property across online and offline platforms.</li>';
        $html .= '<li>Identify, screen, and shortlist prospective tenants.</li>';
        $html .= '<li>Coordinate and facilitate execution of the rental agreement between the Owner and Tenant.</li>';
        $html .= '<li>Conduct a property audit (photo/video) before tenant move-in and after move-out, and share the same with the Property Owner.</li>';
        $html .= '<li>Act as a rent collection agent on behalf of the Property Owner (not applicable when the Tenant is a Company, Firm, or Bank).</li>';
        $html .= '<li>Maintain transparent records of all collections and remittances.</li>';
        $html .= '<li>Assist in coordination of tenant-related operational issues.</li>';
        $html .= '<li>Keep the Property Owner informed if the Tenant does not pay rent within 15 days after the due date mentioned in the rental agreement, and provide updates until all dues are cleared.</li>';
        $html .= '<li>Support the eviction process in cases of default, with legal costs borne by the Owner unless otherwise agreed.</li>';
        $html .= '<li>Act in a fiduciary capacity while handling Owner funds.</li>';
        $html .= '<li>Not be responsible for any management, maintenance, or communication related to society matters, unless the issue is created by a Tenant introduced by the Service Provider.</li>';
        $html .= '</ol>';

        $html .= '<p><strong>B. Property Owner\'s Responsibilities:</strong></p>';
        $html .= '<ol style="padding-left: 20px;" start="12">';
        $html .= '<li>Retain full ownership and legal control over the property.</li>';
        $html .= '<li>Approve tenant selection and rental terms, and execute the rental agreement directly with the Tenant.</li>';
        $html .= '<li>Ensure the property is legally compliant and adequately maintained.</li>';
        $html .= '<li>Carry out timely repairs for damages caused due to natural wear and tear or any internal building issue. Re-paint the property after every two (2) years or after two (2) tenants have changed, <strong>whichever is later</strong>.</li>';
        $html .= '<li>Carry out timely repairs for damages caused to the furnishing items, electrical and electronic appliances in the property, due to general/natural wear and tear.</li>';
        $html .= '<li>Bear all statutory liabilities including property tax, income tax, and GST (if applicable).</li>';
        $html .= '<li>Handle all management, maintenance, and communication related to society matters.</li>';
        $html .= '<li>Have the right to refuse a potential tenant with proper reasoning and reporting to the Service Provider.</li>';
        $html .= '</ol>';

        // Section: Commercials
        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">COMMERCIALS</h3>';
        $html .= '<p><strong>A. Standard Rent Collection Service with monthly fee:</strong></p>';
        $html .= '<p>The <strong>Service Provider</strong> shall charge <strong>'.$badge('fee_percentage', 'Fee %').'% of the monthly rent as Service Fee</strong> (for an un-furnished property) when the property is occupied. This Service Fee is exclusive of GST (whenever applicable).</p>';
        $html .= '<p>Collection of rent against the property shall be the <strong>Service Provider\'s</strong> responsibility. Electricity bills, water bills, apartment maintenance charges (wherever applicable), and similar expenses will be paid directly to the Owner.</p>';
        $html .= '<p>The <strong>Security Deposit</strong> collected from the Tenant shall be held with the <strong>Service Provider</strong> and will be refunded directly to the Tenant as per the terms and conditions of the rental agreement signed between the Property Owner and the Tenant.</p>';
        $html .= '<p>In case of continuous rent payment default by the Tenant while retaining possession of the property, the <strong>Service Provider</strong> will cover rental payments for a maximum of <strong>two (2) defaulting months</strong> only.</p>';

        $html .= '<p><strong>B. Rent-Collection Service with Yearly fee:</strong></p>';
        $html .= '<p>The <strong>Service Provider</strong> shall collect rent/license fee/security deposit, and any other applicable fees from the Tenant and remit the same to the Owner\'s bank account. A Service Fee equivalent to <strong>One (1) month\'s rental amount shall be charged yearly, exclusive of GST (wherever applicable)</strong>. This Service Fee shall be payable to the <strong>Service Provider</strong> within the first month of the Tenant\'s move-in and subsequently within the first month of each rental agreement renewal. Unlike the Standard Rent Collection Service, this model is not contingent on the occupancy status of the property. Under this arrangement, the <strong>Service Provider</strong> shall be responsible for all services related to payments, including collection and timely remittance.</p>';

        $html .= '<p><strong>C. Non-Rent-Collection Service:</strong></p>';
        $html .= '<p>Where rent collection services are not availed, and rent/license fee/security deposit, and any other applicable fees are paid directly to the Owner\'s bank account by the Tenant, a <strong>Service Fee equivalent to One (1) month\'s rental amount shall be charged yearly to the Property Owner, exclusive of GST (wherever applicable)</strong>. This Service Fee shall be payable to the <strong>Service Provider</strong> within the first month of the Tenant\'s move-in and subsequently within the first month of each rental agreement renewal. Under this arrangement, the <strong>Service Provider</strong> shall bear no responsibility for any payment-related services, including but not limited to rent guarantee, timely rent collection, or security deposit refunds.</p>';

        $html .= '<p>The Owner and the Service Provider each reserve the right to propose a change in the applicable <strong>Commercial model - A, B, or C</strong> - based on the nature and profile of the Tenant. However, any such change shall be mutually agreed upon and approved by both parties in writing prior to the execution of the relevant rental agreement or any modification thereof.<br>';
        $html .= '<strong>The Financial Model chosen by the Owner is '.$badge('financial_model_name', 'Chosen Model').'.</strong></p>';

        // Section: Taxes & Reconciliation
        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">TAXES &amp; RECONCILIATION</h3>';
        $html .= '<p>The <strong>Service Provider</strong> does not take responsibility for any default in payment of applicable taxes by the <strong>Property Owner</strong>.</p>';
        $html .= '<p>Reconciliation of accounts will take place between the <strong>5th to 10th of each calendar month</strong>. If the last due date falls on a Sunday or public holiday, reconciliation will be done on the next working day. In case of any disputes, the same must be raised and settled by the <strong>20th day</strong> of that month. Subsequent to resolution, payment will be made by the Service Provider online or by cheque.</p>';
        $html .= '<p>The <strong>Service Provider</strong> is not responsible for any failure to perform its obligations under this agreement if it is prevented or delayed by an event of <strong>force majeure</strong>.</p>';

        // Section: Duration & Termination
        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">DURATION &amp; TERMINATION</h3>';
        $html .= '<p>This agreement will start on <strong>'.$badge('start_date', 'Start Date').'</strong>. The agreement can be terminated with a <strong>60-days written notice</strong> in advance given by either party.</p>';

        // Section: Confidentiality & Disputes
        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">CONFIDENTIALITY</h3>';
        $html .= '<p>All terms and conditions of this agreement shall remain confidential during the period of contract. In case of termination, the <strong>Property Owner</strong> will be liable to abide by the terms of confidentiality for <strong>18 months</strong> from the date of termination.</p>';

        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">RIGHTS &amp; EXCLUSIVITY</h3>';
        $html .= '<p>The <strong>Service Provider</strong> will have no ownership rights over the <strong>Property Owner\'s</strong> property or furnishing items. The property will continue to be under the Property Owner\'s ownership or lease agreement.</p>';

        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">DISPUTES</h3>';
        $html .= '<p>All disputes between the parties will fall under the legal jurisdiction of the <strong>Gauhati High Court</strong>.</p>';

        // Signatures Block
        $html .= '<div style="margin-top: 40px; padding-top: 20px; border-top: 1px dashed #cbd5e1;">';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr>';
        $html .= '<td style="width: 50%; vertical-align: top; padding: 10px;">';
        $html .= '<div style="border-bottom: 1px solid #000; height: 35px; width: 90%; margin-bottom: 6px;"></div>';
        $html .= '<strong>Service Provider</strong><br><em>Dwelly (Assam Alay)</em>';
        $html .= '</td>';
        $html .= '<td style="width: 50%; vertical-align: top; padding: 10px;">';
        $html .= '<div style="border-bottom: 1px solid #000; height: 35px; width: 90%; margin-bottom: 6px;"></div>';
        if (! empty($params['is_signatory_different'])) {
            $html .= '<strong>'.$badge('signatory_name', 'Signatory Name').'</strong><br><em>(Authorized Signatory for Property Owner)</em>';
        } else {
            $html .= '<strong>'.$badge('owner_name', 'Property Owner').'</strong><br><em>(Property Owner)</em>';
        }
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</div>';

        // Annexure I: Bank Account Mandate (centered by default)
        $html .= '<hr style="margin: 40px 0; border: none; border-top: 2px solid #cbd5e1;">';
        $html .= '<h2 style="text-align: center; font-size: 20px; margin: 0 0 5px 0;">Dwelly</h2>';
        $html .= '<h3 style="text-align: center; font-size: 16px; margin: 0 0 4px 0;">Annexure I – Account Information</h3>';
        $html .= '<h4 style="text-align: center; font-size: 14px; margin: 4px 0 20px 0; color: #475569;">RTGS / NEFT / E-Payment Mandate Form</h4>';

        $html .= '<p><strong>Date:</strong> '.$badge('start_date', 'Date').'<br>';
        $html .= '<strong>Sub:</strong> Authorization to effect payments through RTGS / NEFT / Electronic Payment Platform</p>';
        $html .= '<p>I/We hereby request you to effect all payments due to us from the Service Provider to my/our bank account, details of which are given below:</p>';

        $html .= '<p>';
        $html .= '<strong>Name of Property Owner:</strong> '.$badge('owner_name', 'Property Owner').'<br>';
        $html .= '<strong>Beneficiary Account Name:</strong> '.$badge('bank_beneficiary_name', 'Beneficiary').'<br>';
        $html .= '<strong>Name of the Bank:</strong> '.$badge('bank_name', 'Bank').'<br>';
        $html .= '<strong>Address of the Bank:</strong> '.$badge('bank_address', 'Bank Branch').'<br>';
        $html .= '<strong>Bank Account No.:</strong> '.$badge('bank_account_number', 'Account No').'<br>';
        $html .= '<strong>Account Type:</strong> '.$badge('bank_account_type', 'Account Type').'<br>';
        $html .= '<strong>IFSC Code:</strong> '.$badge('bank_ifsc_code', 'IFSC').'<br>';
        $html .= '</p>';

        return $html;
    }

    protected function getDefaultAddendumContent(Mou $mou, \Closure $badge, array $params): string
    {
        $html = '';

        // Addendum Header (centered by default)
        $html .= '<h1 style="text-align: center; font-size: 26px; margin: 0 0 6px 0; color: #1e293b;">Dwelly</h1>';
        $html .= '<h3 style="text-align: center; font-size: 17px; margin: 0 0 6px 0; text-decoration: underline; color: #334155;">MOU Addendum / Agreement Amendment</h3>';
        $html .= '<p style="text-align: center; font-size: 12px; color: #64748b; margin-top: 4px; margin-bottom: 25px;">';
        $html .= 'Addendum No: '.$badge('mou_number', 'MOU Number').' | Date: '.$badge('start_date', 'Date').' | Version: '.$badge('mou_version', 'Version');
        $html .= '</p>';

        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">1. PARTIES &amp; PROPERTY DETAILS</h3>';
        $html .= '<p>This Addendum amends the terms of the existing Operational &amp; Marketing Services Agreement between <strong>Dwelly (Assam Alay)</strong> (Service Provider) and:</p>';
        $html .= '<p>';
        $html .= '<strong>Property Owner:</strong> '.$badge('owner_name', 'Owner Name').'<br>';
        $html .= '<strong>Property Address:</strong> '.$badge('property_address', 'Property Address').'<br>';
        $html .= '<strong>Amendment Type:</strong> '.e($mou->type?->label() ?? 'General Update').'<br>';
        $html .= '</p>';

        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">2. RECITALS &amp; BACKGROUND</h3>';
        $html .= '<p>WHEREAS the parties entered into the Principal Agreement for marketing and management of the property;</p>';
        $html .= '<p>WHEREAS the parties now mutually agree to amend, modify, or add specific operational/commercial terms as detailed below;</p>';

        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">3. AMENDED TERMS &amp; SPECIAL CONDITIONS</h3>';
        $html .= '<p>The following terms and commercial conditions shall supersede and override any contrary clauses in the Principal Agreement:</p>';
        $html .= '<ul style="padding-left: 20px;">';
        $html .= '<li><strong>Financial Model:</strong> '.$badge('financial_model_name', 'Model').'</li>';
        $html .= '<li><strong>Dwelly Fee Percentage:</strong> '.$badge('fee_percentage', 'Fee %').'%</li>';
        $html .= '<li><strong>Effective Date:</strong> '.$badge('start_date', 'Date').'</li>';
        $html .= '<li>[Add custom terms or modifications here]</li>';
        $html .= '</ul>';

        $html .= '<h3 style="font-size: 15px; font-weight: bold; text-decoration: underline; margin-top: 20px; color: #0f172a;">4. FULL FORCE &amp; EFFECT</h3>';
        $html .= '<p>Except as expressly modified by this Addendum, all other covenants, representations, warranties, and obligations under the Principal Agreement remain in full legal force and effect.</p>';

        $html .= '<div style="margin-top: 40px; padding-top: 20px; border-top: 1px dashed #cbd5e1;">';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr>';
        $html .= '<td style="width: 50%; vertical-align: top; padding: 10px;">';
        $html .= '<div style="border-bottom: 1px solid #000; height: 35px; width: 90%; margin-bottom: 6px;"></div>';
        $html .= '<strong>Service Provider</strong><br><em>Dwelly (Assam Alay)</em>';
        $html .= '</td>';
        $html .= '<td style="width: 50%; vertical-align: top; padding: 10px;">';
        $html .= '<div style="border-bottom: 1px solid #000; height: 35px; width: 90%; margin-bottom: 6px;"></div>';
        $html .= '<strong>'.$badge('owner_name', 'Property Owner').'</strong><br><em>(Property Owner)</em>';
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Compiles customized editor HTML into clean, valid, professional PDF HTML.
     * Replaces all locked badges with authoritative values from the database,
     * ensuring tamper-proof legal document compilation.
     */
    public function compileForPdf(Mou $mou, ?string $content = null): string
    {
        $rawHtml = $content ?? $mou->agreement_content ?? $this->getDefaultContent($mou, false);
        $params = $this->getResolvedParameters($mou);

        // 1. Replace <span data-param="KEY"> or <span data-id="KEY"> (TipTap mergeTag) with clean underlined legal values
        $compiled = preg_replace_callback(
            '/<span[^>]*(?:data-param|data-id)=["\']([^"\']+)["\'][^>]*>.*?<\/span>/is',
            function ($matches) use ($params) {
                $key = $matches[1];
                $val = $params[$key] ?? '';

                return '<span class="form-line" style="display: inline-block; border-bottom: 1px solid #000; min-width: 120px; padding: 0 4px; font-weight: bold;">'.e($val).'</span>';
            },
            $rawHtml
        );

        // 2. Replace {{ KEY }} placeholders if any exist
        $compiled = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            function ($matches) use ($params) {
                $key = $matches[1];
                $val = $params[$key] ?? '';

                return '<span class="form-line" style="display: inline-block; border-bottom: 1px solid #000; min-width: 120px; padding: 0 4px; font-weight: bold;">'.e($val).'</span>';
            },
            $compiled
        );

        // 3. Normalize page breaks if any <hr> or page break marker exists
        $compiled = preg_replace(
            '/<hr[^>]*>/i',
            '<div class="page-break" style="page-break-after: always; height: 1px; visibility: hidden;"></div>',
            $compiled
        );

        return $compiled;
    }
}
