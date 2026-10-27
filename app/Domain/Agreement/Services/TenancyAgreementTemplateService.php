<?php

namespace App\Domain\Agreement\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use NumberFormatter;

class TenancyAgreementTemplateService
{
    /**
     * Resolves all locked parameters from the Tenancy Agreement and linked parties.
     */
    public function getResolvedParameters(TenancyAgreement $agreement): array
    {
        $agreement->loadMissing([
            'property',
            'property.owner',
            'property.owner.individual',
            'property.owner.addresses',
            'roles.party',
            'roles.party.individual',
            'roles.party.addresses',
        ]);

        $owner = $agreement->property?->owner;
        $primaryRole = $agreement->roles->where('is_primary', true)->first();
        $tenant = $primaryRole?->party ?? $agreement->tenants->first();

        $ownerAddress = $owner?->addresses?->where('is_primary', true)->first()?->address_line_1
            ?? $owner?->addresses?->first()?->address_line_1
            ?? '_______________________________';

        $tenantAddress = $tenant?->addresses?->where('is_primary', true)->first()?->address_line_1
            ?? $tenant?->addresses?->first()?->address_line_1
            ?? '_______________________________';

        $propertyAddress = $agreement->property?->building_name
            ? $agreement->property->building_name . ', ' . ($agreement->property->address_line_1 ?? $agreement->property->location ?? '')
            : ($agreement->property?->address_line_1 ?? $agreement->property?->location ?? '_______________________________');

        $rentInWords = $this->numberToWords((int) ($agreement->rent_amount ?? 0));
        $depositInWords = $this->numberToWords((int) ($agreement->security_deposit ?? 0));
        $docCharge = (float) ($agreement->documentation_charge ?? ($agreement->is_renewal ? 1000.00 : 1500.00));
        $docChargeInWords = $this->numberToWords((int) $docCharge);

        $annexure1BankDetails = TenancyAgreementForm::getAnnexureIBankDetails($agreement, $agreement->property_id);
        $tenantBankDetails = $agreement->tenant_bank_details ?? [];

        $secList = [];
        if (! empty($agreement->secondary_tenants) && is_array($agreement->secondary_tenants)) {
            foreach ($agreement->secondary_tenants as $st) {
                if (! empty($st['name'])) {
                    $secList[] = e($st['name']) . (! empty($st['relationship']) ? ' (' . e($st['relationship']) . ')' : '');
                }
            }
        }
        $secondaryTenantsText = ! empty($secList) ? implode(', ', $secList) : '';

        return [
            'owner_name' => $owner?->display_name ?? 'Property Owner',
            'owner_parent_name' => $owner?->individual?->parent_name ?? '',
            'owner_address' => $ownerAddress,
            'tenant_name' => $tenant?->display_name ?? 'Tenant Name',
            'tenant_parent_name' => $tenant?->individual?->parent_name ?? '',
            'tenant_address' => $tenantAddress,
            'tenant_phone' => $tenant?->phone ?? '_________________',
            'tenant_aadhaar' => $tenant?->individual?->aadhaar_number ?? '_________________',
            'tenant_pan' => $tenant?->individual?->pan_number ?? '_________________',
            'secondary_tenants' => $secondaryTenantsText,
            'property_address' => $propertyAddress,
            'start_date' => $agreement->start_date ? $agreement->start_date->format('jS F Y') : '________',
            'end_date' => $agreement->end_date ? $agreement->end_date->format('jS F Y') : '________',
            'rent_amount' => number_format((float) ($agreement->rent_amount ?? 0), 2),
            'rent_amount_words' => $rentInWords,
            'security_deposit' => number_format((float) ($agreement->security_deposit ?? 0), 2),
            'security_deposit_words' => $depositInWords,
            'security_deposit_notes' => $agreement->security_deposit_notes ?? '',
            'lock_in_period' => ($agreement->lock_in_period_months ?? 6) . ' months',
            'notice_period' => ($agreement->notice_period_days ?? 30) . ' days',
            'apdcl_consumer_id' => $agreement->apdcl_consumer_id ?? '_________________',
            'doc_charge' => number_format($docCharge, 2),
            'doc_charge_words' => $docChargeInWords,
            'annexure1_beneficiary_name' => $annexure1BankDetails['beneficiary_name'] ?? 'ASSAM ALAY',
            'annexure1_bank_name' => $annexure1BankDetails['bank_name'] ?? 'IndusInd Bank',
            'annexure1_bank_address' => $annexure1BankDetails['bank_address'] ?? 'Beltola, Guwahati',
            'annexure1_account_no' => $annexure1BankDetails['account_number'] ?? '201025429005',
            'annexure1_account_type' => $annexure1BankDetails['account_type'] ?? 'Current',
            'annexure1_ifsc' => $annexure1BankDetails['ifsc_code'] ?? 'INDB0000662',
            'annexure2_account_holder' => $tenantBankDetails['account_holder_name'] ?? $tenant?->display_name ?? 'Tenant',
            'annexure2_bank_name' => $tenantBankDetails['bank_name'] ?? '_________________',
            'annexure2_account_no' => $tenantBankDetails['account_number'] ?? '_________________',
            'annexure2_ifsc' => $tenantBankDetails['ifsc_code'] ?? '_________________',
            'agreement_code' => $agreement->code ?? 'Draft',
            'agreement_version' => (string) ($agreement->version ?? 1),
            'special_terms' => $agreement->special_terms ?? '',
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
     */
    public function getMergeTags(?TenancyAgreement $agreement = null): array
    {
        $params = $agreement ? $this->getResolvedParameters($agreement) : [];

        $format = function (string $key, string $label) use ($params) {
            $val = $params[$key] ?? null;
            if (filled($val)) {
                return '🔒 '.$label.': '.$val;
            }

            return '🔒 '.$label.': (Not provided)';
        };

        return [
            'owner_name' => $format('owner_name', 'Landlord / Licensor Name'),
            'owner_parent_name' => $format('owner_parent_name', 'Landlord Parent Name'),
            'owner_address' => $format('owner_address', 'Landlord Address'),
            'tenant_name' => $format('tenant_name', 'Tenant / Licensee Name'),
            'tenant_parent_name' => $format('tenant_parent_name', 'Tenant Parent Name'),
            'tenant_address' => $format('tenant_address', 'Tenant Address'),
            'tenant_phone' => $format('tenant_phone', 'Tenant Phone'),
            'tenant_aadhaar' => $format('tenant_aadhaar', 'Tenant Aadhaar Number'),
            'tenant_pan' => $format('tenant_pan', 'Tenant PAN Number'),
            'property_address' => $format('property_address', 'Property Address'),
            'start_date' => $format('start_date', 'Agreement Start Date'),
            'end_date' => $format('end_date', 'Agreement End Date'),
            'rent_amount' => $format('rent_amount', 'Monthly Rent (INR)'),
            'rent_amount_words' => $format('rent_amount_words', 'Rent in Words'),
            'security_deposit' => $format('security_deposit', 'Security Deposit (INR)'),
            'security_deposit_words' => $format('security_deposit_words', 'Deposit in Words'),
            'lock_in_period' => $format('lock_in_period', 'Lock-In Period'),
            'notice_period' => $format('notice_period', 'Notice Period'),
            'apdcl_consumer_id' => $format('apdcl_consumer_id', 'APDCL Smart Meter ID'),
            'doc_charge' => $format('doc_charge', 'Documentation Fee'),
            'annexure1_beneficiary_name' => $format('annexure1_beneficiary_name', 'Collection Beneficiary'),
            'annexure1_bank_name' => $format('annexure1_bank_name', 'Collection Bank Name'),
            'annexure1_account_no' => $format('annexure1_account_no', 'Collection Account No'),
            'annexure1_ifsc' => $format('annexure1_ifsc', 'Collection IFSC'),
            'annexure2_account_no' => $format('annexure2_account_no', 'Deposit Refund Account No'),
            'agreement_code' => $format('agreement_code', 'Agreement Code'),
            'agreement_version' => $format('agreement_version', 'Version'),
        ];
    }

    /**
     * Generates default HTML agreement content pre-filled with locked parameters.
     */
    public function getDefaultContent(TenancyAgreement $agreement, bool $forEditor = true): string
    {
        $params = $this->getResolvedParameters($agreement);

        $badge = function (string $key, string $label) use ($params, $forEditor) {
            $val = $params[$key] ?? '';
            if (! $forEditor) {
                return '<span class="form-line" style="font-weight: bold;">'.e($val).'</span>';
            }

            return $this->renderLockedBadge($key, $label, $val);
        };

        $html = '';

        // Header
        $html .= '<h2 style="text-align: center; font-size: 20px; font-weight: bold; text-decoration: underline; text-transform: uppercase; margin-bottom: 20px;">LEAVE AND LICENSE AGREEMENT</h2>';
        $html .= '<p style="text-align: center; font-size: 12px; color: #64748b; margin-top: 4px; margin-bottom: 20px;">Ref: '.$badge('agreement_code', 'Agreement Ref').' | Version: '.$badge('agreement_version', 'Version').'</p>';

        // Recital & Parties
        $html .= '<p>This Leave and License Agreement (“Agreement”) is made and executed in <strong>Guwahati</strong> on this '.$badge('start_date', 'Execution Date').' BETWEEN:</p>';

        $html .= '<p>';
        $html .= '<strong>'.$badge('owner_name', 'Licensor Name').'</strong>, ';
        if (! empty($params['owner_parent_name'])) {
            $html .= 'S/o or D/o '.$badge('owner_parent_name', 'Parent Name').', ';
        }
        $html .= 'resident of '.$badge('owner_address', 'Licensor Address').' ';
        $html .= '(hereinafter referred to as the <strong>“Licensor”</strong>, which shall mean and include its legal representatives, executors, assignees, employees and administrators)';
        $html .= '</p>';

        $html .= '<p style="text-align: center; font-weight: bold; margin: 15px 0;">AND</p>';

        $html .= '<p>';
        $html .= '<strong>'.$badge('tenant_name', 'Licensee Name').'</strong>, ';
        if (! empty($params['tenant_parent_name'])) {
            $html .= 'C/o '.$badge('tenant_parent_name', 'Parent Name').', ';
        }
        $html .= 'resident of '.$badge('tenant_address', 'Licensee Address').', ';
        $html .= 'with Aadhaar number '.$badge('tenant_aadhaar', 'Aadhaar').', ';
        $html .= 'Phone number: '.$badge('tenant_phone', 'Phone').' ';
        $html .= '(hereinafter referred to as the <strong>“Licensee”</strong>, which shall mean and include its legal representatives, executors, assignees and administrators).';
        $html .= '</p>';

        if (! empty($params['secondary_tenants'])) {
            $html .= '<p style="font-size: 13px; font-style: italic; color: #475569; background: #f8fafc; padding: 8px 12px; border-radius: 6px; border: 1px solid #e2e8f0;">';
            $html .= '<strong>Co-occupants / Family members residing with Licensee:</strong> '.$badge('secondary_tenants', 'Secondary Tenants');
            $html .= '</p>';
        }

        $html .= '<p>';
        $html .= 'AND WHEREAS the ‘Licensor’ is the absolute owner in full possession of the constructed structure described as ';
        $html .= '<strong>'.$badge('property_address', 'Property Address').'</strong> (hereinafter referred to as the <strong>“Licensed Premises”</strong>) and is desirous of giving the said premises.';
        $html .= '</p>';

        $html .= '<p>';
        $html .= 'AND WHEREAS the Licensee herein is in need of the above mentioned premises for residential use and has approached the Licensor with a request to use and occupy the premises on temporary basis for a period commencing from ';
        $html .= ''.$badge('start_date', 'Start Date').' and ending on '.$badge('end_date', 'End Date').' as per the terms and conditions hereafter appearing herein below:';
        $html .= '</p>';

        $html .= '<p>Now therefore it is hereby agreed to, declared and recorded by and between the parties hereto as follows:-</p>';

        // Clause: Commencement and Period
        $html .= '<h3 style="font-size: 14px; font-weight: bold; text-decoration: underline; margin-top: 18px; color: #0f172a;">Commencement and Period:</h3>';
        $html .= '<ul style="padding-left: 20px; line-height: 1.6;">';
        $html .= '<li>This Agreement shall commence from <strong>'.$badge('start_date', 'Start Date').'</strong> and ending on <strong>'.$badge('end_date', 'End Date').'</strong>. If the Licensee vacates the leased premises within the first <strong>'.$badge('lock_in_period', 'Lock-In Period').'</strong> from the start of the rental period then the entire security deposit will be charged as cancellation fee. Exceptions to be granted for medical emergency/crisis scenarios/official transfers upon providing supporting documents as proof.</li>';
        $html .= '<li>The agreement can be renewed after the end of each tenure as per terms mentioned in the ‘renewal’ section of this agreement.</li>';
        $html .= '</ul>';

        // Clause: Rent & Deposit
        $html .= '<h3 style="font-size: 14px; font-weight: bold; text-decoration: underline; margin-top: 18px; color: #0f172a;">Rent &amp; Deposit:</h3>';
        $html .= '<ul style="padding-left: 20px; line-height: 1.6;">';
        $html .= '<li>The Licensee shall pay a license fee of <strong>Rupees '.$badge('rent_amount_words', 'Rent in Words').' (INR '.$badge('rent_amount', 'Monthly Rent').')</strong> per month for the use of the said Licensed premises directly to the Licensor’s authorised representative’s bank account details mentioned in <strong>Annexure - I</strong>, rent collection and the flat is managed by the authorised collection representative <strong>Assam Alay (Dwelly)</strong>.</li>';
        $html .= '<li>The License amount shall be payable within the <strong>first five days</strong> of the concerned month.</li>';
        $html .= '<li>In the event of failure of payment after the 15th day of the month the Licensor has every right to cancel the agreement with immediate effect without any notice. In such a situation the entire security amount will be used to compensate for the default in payment and the Licensed premises will be claimed back by the Licensor immediately.</li>';
        $html .= '<li>The security deposit of <strong>Rupees '.$badge('security_deposit_words', 'Deposit in Words').' (INR '.$badge('security_deposit', 'Deposit').')</strong> shall be paid to the Licensor before moving-in to the said premises and the same interest free amount shall be returned to the Licensee within 7 to 10 bank working days of vacating the property and the key to the Licensed premise has been handed over to the Licensor.</li>';
        $html .= '<li>The keys of the property will only be handed over to the Licensee once the entire security deposit has been paid to the bank account mentioned in <strong>Annexure - I</strong>.</li>';
        $html .= '<li>The security deposit will <strong>NOT BE ADJUSTED</strong> against any month’s payable amount.</li>';
        $html .= '</ul>';

        // Clause: Maintenance Charge
        $html .= '<h3 style="font-size: 14px; font-weight: bold; text-decoration: underline; margin-top: 18px; color: #0f172a;">Maintenance Charge:</h3>';
        $html .= '<ul style="padding-left: 20px; line-height: 1.6;">';
        $html .= '<li>The outgoings towards all Government &amp; Municipal rates and taxes and other levies shall be cleared and paid by the Licensor as applicable.</li>';
        $html .= '</ul>';

        // Clause: Electricity & Other charges
        $html .= '<h3 style="font-size: 14px; font-weight: bold; text-decoration: underline; margin-top: 18px; color: #0f172a;">Electricity &amp; Other charges:</h3>';
        $html .= '<ul style="padding-left: 20px; line-height: 1.6;">';
        $html .= '<li>The Licensee herein shall pay the electricity bills timely for the energy consumed as per the tariff bills from the electricity supply agencies. The APDCL prepaid smart meter consumer ID is <strong>'.$badge('apdcl_consumer_id', 'APDCL Consumer ID').'</strong>.</li>';
        $html .= '<li>That the Licensee shall ensure that all electricity, water and gas (if any) connections to the said house are maintained in good condition.</li>';
        $html .= '<li>The Licensor shall ensure that all charges for electricity and water consumed in respect of the said apartment/flat prior to the commencement of the agreement hereby granted are paid by the Licensor.</li>';
        $html .= '<li>The society/maintenance fee (currently included in the rent) shall be paid by the Licensee. However, if the society fee increases in the future then the corresponding amount has to be paid by the Licensee.</li>';
        $html .= '</ul>';

        // Clause: Furniture/Fittings & Appliances
        $html .= '<h3 style="font-size: 14px; font-weight: bold; text-decoration: underline; margin-top: 18px; color: #0f172a;">Furniture/Fittings &amp; Appliances:</h3>';
        $html .= '<ul style="padding-left: 20px; line-height: 1.6;">';
        $html .= '<li>The Licensed premises shall be kept in the best condition possible. If any of the items (as per the verified items documented in <strong>Annexure - III Move-In Audit</strong>) along with plumbing fittings, electrical fittings are damaged or lost during the period of the Licensee’s stay due to Licensee’s wilful misconduct or negligence, the cost of the item/s has to be borne by the Licensee on actual Maximum Retail Price of the product (without any depreciation).</li>';
        $html .= '<li>No tampering with any electrical boards and no pasting of any materials or putting of nails on the walls shall be permitted. Excessive drilling, nailing or installation of multiple fixtures that may damage the walls shall not be permitted and any repair/repainting cost arising out of such damage shall be borne by the Licensee.</li>';
        $html .= '<li>The Property shall be returned by the Licensee in the same condition as documented in the Move-In Audit photographs, subject only to reasonable wear and tear arising from building seepage or structural leakage.</li>';
        $html .= '<li>The Licensee shall be responsible for the regular cleaning and upkeep of all appliances provided in the Premises such as (but not limited to) the air-conditioner and kitchen chimney, at intervals of approximately every 3–4 months.</li>';
        $html .= '<li>Any issues (non-structural) of the apartment reported after 7 days of Move-In to be borne by Licensee.</li>';
        $html .= '</ul>';

        // Clause: Use
        $html .= '<h3 style="font-size: 14px; font-weight: bold; text-decoration: underline; margin-top: 18px; color: #0f172a;">Use:</h3>';
        $html .= '<ul style="padding-left: 20px; line-height: 1.6;">';
        $html .= '<li>The Licensee shall use the said premises for residential purpose only and shall not sub-let/sub-rent it to anyone else.</li>';
        $html .= '<li>The Leave and License Agreement shall not be used by the Licensee for any sort of loan application/ credit card application address verification purposes etc.</li>';
        $html .= '<li>If the Tenant Police Verification report of the Licensee submitted by the authorised Service Provider comes out to be negative or rejected by Assam Police Department, the Licensee shall need to vacate the premises immediately within 24 hours.</li>';
        $html .= '</ul>';

        // Clause: Possession / Termination & Renewal
        $html .= '<h3 style="font-size: 14px; font-weight: bold; text-decoration: underline; margin-top: 18px; color: #0f172a;">Possession / Termination &amp; Renewal:</h3>';
        $html .= '<ul style="padding-left: 20px; line-height: 1.6;">';
        $html .= '<li>If the Licensee wants to vacate the premises before the agreement tenure then <strong>'.$badge('notice_period', 'Notice Period').'</strong> notice period needs to be issued at the beginning of a month.</li>';
        $html .= '<li>The Licensor has an option to renew this agreement with a <strong>5% increase</strong> in license fee upon mutually agreed terms. Minimum INR 1,000.00 will be charged by the Service provider for renewal paperwork.</li>';
        $html .= '<li>Minimum Rupees Two Thousand only (INR 2,000.00) will be deducted from the security deposit at the time of refund towards cleaning charges.</li>';
        $html .= '<li>Rupees '.$badge('doc_charge_words', 'Documentation Fee in Words').' only (INR '.$badge('doc_charge', 'Documentation Fee').') will be charged by the Service provider to the Licensee for the documentation paperwork.</li>';
        $html .= '</ul>';

        if (! empty($params['special_terms'])) {
            $html .= '<h3 style="font-size: 14px; font-weight: bold; text-decoration: underline; margin-top: 18px; color: #0f172a;">Special Terms &amp; Conditions:</h3>';
            $html .= '<p>'.nl2br(e($params['special_terms'])).'</p>';
        }

        // Witness & Signatures
        $html .= '<p style="margin-top: 30px;">IN WITNESS WHEREOF, THE PARTIES TO HAVE HEREUNTO SET AND SUBSCRIBED THEIR RESPECTIVE HANDS ON THE DAY AND THE YEAR FIRST HEREIN ABOVE WRITTEN.</p>';
        $html .= '<div style="margin-top: 25px; padding-top: 15px; border-top: 1px dashed #cbd5e1;">';
        $html .= '<table style="width: 100%; border-collapse: collapse;">';
        $html .= '<tr>';
        $html .= '<td style="width: 50%; vertical-align: top; padding: 10px;">';
        $html .= '<div style="border-bottom: 1px solid #000; height: 35px; width: 90%; margin-bottom: 6px;"></div>';
        $html .= '<strong>Licensor:</strong> '.$badge('owner_name', 'Licensor');
        $html .= '</td>';
        $html .= '<td style="width: 50%; vertical-align: top; padding: 10px;">';
        $html .= '<div style="border-bottom: 1px solid #000; height: 35px; width: 90%; margin-bottom: 6px;"></div>';
        $html .= '<strong>Licensee:</strong> '.$badge('tenant_name', 'Licensee');
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '<tr>';
        $html .= '<td style="width: 50%; vertical-align: top; padding: 10px; padding-top: 25px;">';
        $html .= '<div style="border-bottom: 1px solid #000; height: 35px; width: 90%; margin-bottom: 6px;"></div>';
        $html .= '<strong>Witness 1:</strong> _______________________';
        $html .= '</td>';
        $html .= '<td style="width: 50%; vertical-align: top; padding: 10px; padding-top: 25px;">';
        $html .= '<div style="border-bottom: 1px solid #000; height: 35px; width: 90%; margin-bottom: 6px;"></div>';
        $html .= '<strong>Witness 2:</strong> _______________________';
        $html .= '</td>';
        $html .= '</tr>';
        $html .= '</table>';
        $html .= '</div>';

        // Annexure I
        $html .= '<hr style="margin: 35px 0; border: none; border-top: 2px solid #cbd5e1;">';
        $html .= '<h3 style="text-align: center; font-size: 16px; margin: 0 0 4px 0; text-transform: uppercase;">Annexure I – Rent Deposit Account Information</h3>';
        $html .= '<h4 style="text-align: center; font-size: 13px; margin: 4px 0 15px 0; color: #475569;">RTGS / NEFT / E-Payment Platform</h4>';
        $html .= '<p>Sub: Authorization to effect payments through RTGS/NEFT/Electronic Payment Platform</p>';
        $html .= '<p>I/We, hereby, request you to effect all rent payments due to the following bank account details:</p>';
        $html .= '<p>';
        $html .= '<strong>Beneficiary Name:</strong> '.$badge('annexure1_beneficiary_name', 'Beneficiary').'<br>';
        $html .= '<strong>Name of the Bank:</strong> '.$badge('annexure1_bank_name', 'Bank Name').'<br>';
        $html .= '<strong>Address of the Bank:</strong> '.$badge('annexure1_bank_address', 'Branch Address').'<br>';
        $html .= '<strong>Account No:</strong> '.$badge('annexure1_account_no', 'Account No').'<br>';
        $html .= '<strong>Account Type:</strong> '.$badge('annexure1_account_type', 'Account Type').'<br>';
        $html .= '<strong>IFSC Code:</strong> '.$badge('annexure1_ifsc', 'IFSC Code').'<br>';
        $html .= '</p>';

        // Annexure II
        $html .= '<hr style="margin: 35px 0; border: none; border-top: 2px solid #cbd5e1;">';
        $html .= '<h3 style="text-align: center; font-size: 16px; margin: 0 0 4px 0; text-transform: uppercase;">Annexure II – Security Deposit Refund Account Information</h3>';
        $html .= '<h4 style="text-align: center; font-size: 13px; margin: 4px 0 15px 0; color: #475569;">Tenant Bank Account Mandate Form</h4>';
        $html .= '<p>Sub: Authorization to effect refund of security deposit at the end of tenancy</p>';
        $html .= '<p>I/We, hereby, request you to effect the refund of security deposit due at the time of vacating to the following bank account details:</p>';
        $html .= '<p>';
        $html .= '<strong>Account Holder:</strong> '.$badge('annexure2_account_holder', 'Tenant Account Holder').'<br>';
        $html .= '<strong>Name of the Bank:</strong> '.$badge('annexure2_bank_name', 'Tenant Bank Name').'<br>';
        $html .= '<strong>Account No:</strong> '.$badge('annexure2_account_no', 'Tenant Account No').'<br>';
        $html .= '<strong>IFSC Code:</strong> '.$badge('annexure2_ifsc', 'Tenant IFSC Code').'<br>';
        $html .= '</p>';

        return $html;
    }

    /**
     * Compiles customized editor HTML into clean printable HTML for DomPDF.
     */
    public function compileForPdf(TenancyAgreement $agreement, ?string $content = null): string
    {
        $rawHtml = $content ?? $agreement->agreement_content ?? $this->getDefaultContent($agreement, false);
        $params = $this->getResolvedParameters($agreement);

        // 1. Replace mergeTag spans with clean underlined legal values
        $compiled = preg_replace_callback(
            '/<span[^>]*(?:data-param|data-id)=["\']([^"\']+)["\'][^>]*>.*?<\/span>/is',
            function ($matches) use ($params) {
                $key = $matches[1];
                $val = $params[$key] ?? '';

                return '<span class="form-line" style="display: inline-block; border-bottom: 1px solid #000; min-width: 80px; padding: 0 4px; font-weight: bold;">'.e($val).'</span>';
            },
            $rawHtml
        );

        // 2. Replace {{ KEY }} placeholders if any exist
        $compiled = preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            function ($matches) use ($params) {
                $key = $matches[1];
                $val = $params[$key] ?? '';

                return '<span class="form-line" style="display: inline-block; border-bottom: 1px solid #000; min-width: 80px; padding: 0 4px; font-weight: bold;">'.e($val).'</span>';
            },
            $compiled
        );

        // 3. Normalize page breaks if any <hr> exists
        $compiled = preg_replace(
            '/<hr[^>]*>/i',
            '<div class="page-break" style="page-break-after: always; height: 1px; visibility: hidden;"></div>',
            $compiled
        );

        return $compiled;
    }

    private function numberToWords(int $number): string
    {
        if (class_exists('NumberFormatter')) {
            $formatter = new NumberFormatter('en_IN', NumberFormatter::SPELLOUT);

            return ucwords($formatter->format($number));
        }

        return (string) $number;
    }
}
