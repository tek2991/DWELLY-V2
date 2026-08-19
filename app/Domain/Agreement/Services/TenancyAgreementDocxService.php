<?php

namespace App\Domain\Agreement\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use App\Filament\Resources\TenancyAgreements\Schemas\TenancyAgreementForm;
use NumberFormatter;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;

class TenancyAgreementDocxService
{
    public function generateDocx(TenancyAgreement $agreement): string
    {
        $agreement->load([
            'property',
            'property.owner',
            'property.rooms.roomDefinition',
            'property.inventories.inventoryType',
            'audit',
            'audit.categories.items.source',
            'roles.party',
            'roles.party.individual',
            'roles.party.addresses',
        ]);

        $owner = $agreement->property?->owner;
        $primaryRole = $agreement->roles->where('is_primary', true)->first();
        $tenant = $primaryRole?->party ?? $agreement->tenants->first();

        $ownerAddress = $owner?->addresses->where('is_primary', true)->first()?->address_line_1
            ?? $owner?->addresses->first()?->address_line_1
            ?? '_______________________________';

        $tenantAddress = $tenant?->addresses->where('is_primary', true)->first()?->address_line_1
            ?? $tenant?->addresses->first()?->address_line_1
            ?? '_______________________________';

        $propertyAddress = $agreement->property?->address
            ?? $agreement->property?->location
            ?? $agreement->property?->address_line_1
            ?? '_______________________________';

        $rentInWords = $this->numberToWords((int) $agreement->rent_amount);
        $depositInWords = $this->numberToWords((int) $agreement->security_deposit);
        $tenantBankDetails = $agreement->tenant_bank_details ?? [];

        $annexure1BankDetails = TenancyAgreementForm::getAnnexureIBankDetails($agreement, $agreement->property_id);

        $phpWord = new PhpWord;
        $section = $phpWord->addSection([
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1200,
            'marginRight' => 1200,
        ]);

        // Footer
        $footer = $section->addFooter();
        $footer->addText(
            $this->esc('Dwelly (Assam Alay), Registered Office: #61, Basistha Road, Beltola, Guwahati, Assam – 781028 | M: +91-80994 94817'),
            ['size' => 8.5, 'color' => '666666', 'italic' => true],
            ['alignment' => Jc::CENTER]
        );

        // Styles
        $headerStyle = ['size' => 15, 'bold' => true, 'underline' => 'single'];
        $titleStyle = ['size' => 11.5, 'bold' => true, 'underline' => 'single'];
        $boldStyle = ['bold' => true];
        $paraStyle = ['spaceAfter' => 120, 'lineHeight' => 1.2];
        $listStyle = ['spaceAfter' => 80, 'lineHeight' => 1.15];

        // Title
        $section->addText($this->esc('LEAVE AND LICENSE AGREEMENT'), $headerStyle, ['alignment' => Jc::CENTER, 'spaceAfter' => 180]);

        // Intro
        $createdDate = $agreement->created_at ? $agreement->created_at->format('jS \d\a\y \o\f F Y') : date('jS \d\a\y \o\f F Y');
        $section->addText($this->esc("This Leave and License Agreement (“Agreement”) is made and executed in Guwahati on this {$createdDate} BETWEEN"), [], $paraStyle);

        $ownerName = $owner->display_name ?? 'Property Owner';
        $parentName = ! empty($owner->individual?->parent_name) ? " S/o or D/o {$owner->individual->parent_name}," : '';
        $section->addText($this->esc("{$ownerName},{$parentName} resident of {$ownerAddress} (hereinafter referred to as the “Licensor”, which shall mean and include its legal representatives, executors, assignees, employees and administrators)"), $boldStyle, $paraStyle);

        $section->addText($this->esc('AND'), ['bold' => true, 'size' => 11], ['alignment' => Jc::CENTER, 'spaceBefore' => 60, 'spaceAfter' => 60]);

        $tenantName = $tenant->display_name ?? 'Tenant Name';
        $tParent = ! empty($tenant->individual?->parent_name) ? " C/o {$tenant->individual->parent_name}," : '';
        $tAadhaar = ! empty($tenant->individual?->aadhaar_number) ? " with Aadhaar number {$tenant->individual->aadhaar_number}," : '';
        $tPhone = $tenant->phone ?? '_________________';
        $section->addText($this->esc("{$tenantName},{$tParent} resident of {$tenantAddress}{$tAadhaar} Phone number: {$tPhone} (hereinafter referred to as the “Licensee”, which shall mean and include its legal representatives, executors, assignees and administrators)."), $boldStyle, $paraStyle);

        // Secondary tenants if any
        if (! empty($agreement->secondary_tenants) && is_array($agreement->secondary_tenants)) {
            $secList = [];
            foreach ($agreement->secondary_tenants as $st) {
                if (! empty($st['name'])) {
                    $rel = ! empty($st['relationship']) ? " ({$st['relationship']})" : '';
                    $secList[] = "{$st['name']}{$rel}";
                }
            }
            if (! empty($secList)) {
                $section->addText($this->esc('Co-occupants / Family members residing with Licensee: '.implode(', ', $secList).'.'), ['italic' => true], $paraStyle);
            }
        }

        $propName = $agreement->property?->building_name ?? $agreement->property?->name ?? '';
        $section->addText($this->esc("AND WHEREAS the ‘Licensor’ is the absolute owner in full possession of the constructed structure described as {$propName}, {$propertyAddress} (hereinafter referred to as the “Licensed Premises”) and is desirous of giving the said premises."), [], $paraStyle);

        $startDate = $agreement->start_date ? $agreement->start_date->format('jS F Y') : '________';
        $endDate = $agreement->end_date ? $agreement->end_date->format('jS F Y') : '________';
        $section->addText($this->esc("AND WHEREAS the Licensee herein is in need of the above mentioned premises for residential use and has approached the Licensor with a request to use and occupy the premises on temporary basis for a period commencing from {$startDate} and ending on {$endDate} as per the terms and conditions hereafter appearing herein below:"), [], $paraStyle);

        $section->addText($this->esc('Now therefore it is hereby agreed to, declared and recorded by and between the parties hereto as follows:-'), $boldStyle, ['spaceBefore' => 60, 'spaceAfter' => 100]);

        // 1. Commencement and Period
        $section->addText($this->esc('Commencement and Period:'), $titleStyle, ['spaceBefore' => 100, 'spaceAfter' => 60]);
        $lockInMonths = $agreement->lock_in_period_months ?? 6;
        $section->addListItem($this->esc("This Agreement shall commence from {$startDate} and ending on {$endDate}. If the Licensee vacates the leased premises within the first {$lockInMonths} months from the start of the rental period then the entire security deposit will be charged as cancellation fee. Exceptions to be granted for medical emergency/crisis scenarios/official transfers upon providing supporting documents as proof."), 0, null, null, $listStyle);
        $section->addListItem($this->esc('The agreement can be renewed after the end of each tenure as per terms mentioned in the ‘renewal’ section of this agreement.'), 0, null, null, $listStyle);

        // 2. Rent & Deposit
        $section->addText($this->esc('Rent & Deposit:'), $titleStyle, ['spaceBefore' => 100, 'spaceAfter' => 60]);
        $rentAmt = number_format($agreement->rent_amount ?? 0, 2);
        $depAmt = number_format($agreement->security_deposit ?? 0, 2);
        $depNotes = ! empty($agreement->security_deposit_notes) ? " ({$agreement->security_deposit_notes})" : '';

        $section->addListItem($this->esc("The Licensee shall pay a license fee of Rupees {$rentInWords} (INR {$rentAmt}) per month for the use of the said Licensed premises directly to the Licensor’s authorised representative’s bank account details mentioned in Annexure - I, rent collection and the flat is managed by the authorised collection representative Assam Alay (Dwelly)."), 0, null, null, $listStyle);
        $section->addListItem($this->esc('The License amount shall be payable within the first five days of the concerned month.'), 0, null, null, $listStyle);
        $section->addListItem($this->esc('In the event of failure of payment after the 15th day of the month the Licensor has every right to cancel the agreement with immediate effect without any notice. In such a situation the entire security amount will be used to compensate for the default in payment and the Licensed premises will be claimed back by the Licensor immediately.'), 0, null, null, $listStyle);
        $section->addListItem($this->esc("The two months security deposit of Rupees {$depositInWords} (INR {$depAmt}){$depNotes} shall be paid to the Licensor before moving-in to the said premises and the same interest free amount shall be returned to the Licensee within 7 to 10 bank working days of vacating the property and the key to the Licensed premise has been handed over to the Licensor."), 0, null, null, $listStyle);
        $section->addListItem($this->esc('The keys of the property will only be handed over to the Licensee once the entire security deposit has been paid to the bank account mentioned in Annexure - I.'), 0, null, null, $listStyle);
        $section->addListItem($this->esc('The security deposit will NOT BE ADJUSTED against any month’s payable amount.'), 0, null, null, $listStyle);

        // 3. Maintenance Charge
        $section->addText($this->esc('Maintenance Charge:'), $titleStyle, ['spaceBefore' => 100, 'spaceAfter' => 60]);
        $section->addListItem($this->esc('The outgoings towards all Government & Municipal rates and taxes and other levies shall be cleared and paid by the Licensor as applicable.'), 0, null, null, $listStyle);

        // 4. Electricity & Other charges
        $section->addText($this->esc('Electricity & Other charges:'), $titleStyle, ['spaceBefore' => 100, 'spaceAfter' => 60]);
        $apdcl = $agreement->apdcl_consumer_id ?? '_________________';
        $section->addListItem($this->esc("The Licensee herein shall pay the electricity bills timely for the energy consumed as per the tariff bills from the electricity supply agencies. The APDCL prepaid smart meter consumer ID is {$apdcl}."), 0, null, null, $listStyle);
        $section->addListItem($this->esc('That the Licensee shall ensure that all electricity, water and gas (if any) connections to the said house are maintained in good condition.'), 0, null, null, $listStyle);
        $section->addListItem($this->esc('The Licensor shall ensure that all charges for electricity and water consumed in respect of the said apartment/flat prior to the commencement of the agreement hereby granted are paid by the Licensor.'), 0, null, null, $listStyle);
        $section->addListItem($this->esc('The society/maintenance fee (currently included in the rent) shall be paid by the Licensee. However, if the society fee increases in the future then the corresponding amount has to be paid by the Licensee.'), 0, null, null, $listStyle);

        // 5. Furniture/Fittings & Appliances
        $section->addText($this->esc('Furniture/Fittings & Appliances:'), $titleStyle, ['spaceBefore' => 100, 'spaceAfter' => 60]);
        $section->addListItem($this->esc('The Licensed premises shall be kept in the best condition possible. If any of the items (as per the items mentioned in Annexure - III) along with plumbing fittings, electrical fittings are damaged or lost during the period of the Licensee’s stay due to Licensee’s wilful misconduct or negligence, the cost of the item/s has to be borne by the Licensee on actual Maximum Retail Price of the product (without any depreciation).'), 0, null, null, $listStyle);
        $section->addListItem($this->esc('No tampering with any electrical boards and no pasting of any materials or putting of nails on the walls shall be permitted. Excessive drilling, nailing or installation of multiple fixtures that may damage the walls shall not be permitted and any repair/repainting cost arising out of such damage shall be borne by the Licensee.'), 0, null, null, $listStyle);
        $section->addListItem($this->esc('The Property shall be returned by the Licensee in the same condition as documented in the Move-In Audit photographs, subject only to reasonable wear and tear arising from building seepage or structural leakage.'), 0, null, null, $listStyle);
        $section->addListItem($this->esc('The Licensee shall be responsible for the regular cleaning and upkeep of all appliances provided in the Premises such as (but not limited to) the air-conditioner and kitchen chimney, at intervals of approximately every 3–4 months.'), 0, null, null, $listStyle);
        $section->addListItem($this->esc('Any issues (non-structural) of the apartment reported after 7 days of Move-In to be borne by Licensee.'), 0, null, null, $listStyle);

        // 6. Use
        $section->addText($this->esc('Use:'), $titleStyle, ['spaceBefore' => 100, 'spaceAfter' => 60]);
        $section->addListItem($this->esc('The Licensee shall use the said premises for residential purpose only and shall not sub-let/sub-rent it to anyone else.'), 0, null, null, $listStyle);
        $section->addListItem($this->esc('The Leave and License Agreement shall not be used by the Licensee for any sort of loan application/ credit card application address verification purposes etc.'), 0, null, null, $listStyle);
        $section->addListItem($this->esc('If the Tenant Police Verification report of the Licensee submitted by the authorised Service Provider comes out to be negative or rejected by Assam Police Department, the Licensee shall need to vacate the premises immediately within 24 hours.'), 0, null, null, $listStyle);

        // 7. Possession / Termination & Renewal
        $section->addText($this->esc('Possession / Termination & Renewal:'), $titleStyle, ['spaceBefore' => 100, 'spaceAfter' => 60]);
        $noticeDays = $agreement->notice_period_days ?? 30;
        $section->addListItem($this->esc("If the Licensee wants to vacate the premises before the agreement tenure then {$noticeDays} days' notice period (one month) needs to be issued at the beginning of a month."), 0, null, null, $listStyle);
        $section->addListItem($this->esc('The Licensor has an option to renew this agreement with a 5% increase in license fee upon mutually agreed terms. Minimum INR 1,000.00 will be charged by the Service provider for renewal paperwork.'), 0, null, null, $listStyle);
        $section->addListItem($this->esc('Minimum Rupees Two Thousand only (INR 2,000.00) will be deducted from the security deposit at the time of refund towards cleaning charges.'), 0, null, null, $listStyle);
        $section->addListItem($this->esc('Rupees One Thousand and Five Hundred only (INR 1,500.00) will be charged by the Service provider to the Licensee for the paperwork.'), 0, null, null, $listStyle);

        // Special Terms (if any)
        if (! empty($agreement->special_terms)) {
            $section->addText($this->esc('Special Terms & Conditions:'), $titleStyle, ['spaceBefore' => 100, 'spaceAfter' => 60]);
            $section->addText($this->esc($agreement->special_terms), [], $paraStyle);
        }

        // Signatures Block
        $section->addText($this->esc('IN WITNESS WHEREOF, THE PARTIES TO HAVE HEREUNTO SET AND SUBSCRIBED THEIR RESPECTIVE HANDS ON THE DAY AND THE YEAR FIRST HEREIN ABOVE WRITTEN.'), $boldStyle, ['spaceBefore' => 160, 'spaceAfter' => 120]);

        $sigTable = $section->addTable(['borderSize' => 0, 'cellMargin' => 60]);
        $sigTable->addRow();
        $cellLicensor = $sigTable->addCell(4500);
        $cellLicensor->addText('___________________________________', ['bold' => true]);
        $cellLicensor->addText($this->esc("Licensor: {$ownerName}"), ['bold' => true]);

        $sigTable->addCell(500); // Spacer

        $cellLicensee = $sigTable->addCell(4500);
        $cellLicensee->addText('___________________________________', ['bold' => true]);
        $cellLicensee->addText($this->esc("Licensee: {$tenantName}"), ['bold' => true]);

        $sigTable->addRow();
        $sigTable->addCell(4500)->addTextBreak(1);
        $sigTable->addCell(500);
        $sigTable->addCell(4500);

        $sigTable->addRow();
        $cellW1 = $sigTable->addCell(4500);
        $cellW1->addText('___________________________________', ['bold' => true]);
        $cellW1->addText('Witness 1: _______________________', ['bold' => true]);

        $sigTable->addCell(500);

        $cellW2 = $sigTable->addCell(4500);
        $cellW2->addText('___________________________________', ['bold' => true]);
        $cellW2->addText('Witness 2: _______________________', ['bold' => true]);

        // Annexure I
        $section->addPageBreak();
        $section->addText($this->esc('Annexure I – Rent deposit Account Information'), $headerStyle, ['alignment' => Jc::CENTER]);
        $section->addText($this->esc('RTGS / NEFT / E-Payment Form'), ['size' => 12, 'bold' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);
        $section->addText($this->esc('Sub: Authorization to effect payments through RTGS/NEFT/Electronic Payment Platform'), ['italic' => true], $paraStyle);
        $section->addText($this->esc('I/We, hereby, request you to effect all rent payments due to the following bank account details:'), [], $paraStyle);

        $table1 = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80]);
        $this->addTableRow($table1, 'Beneficiary Name', $annexure1BankDetails['beneficiary_name'] ?? 'ASSAM ALAY');
        $this->addTableRow($table1, 'Name of the Bank', $annexure1BankDetails['bank_name'] ?? 'IndusInd Bank');
        $this->addTableRow($table1, 'Address of the Bank', $annexure1BankDetails['bank_address'] ?? 'Beltola, Guwahati');
        $this->addTableRow($table1, 'Bank Account No', $annexure1BankDetails['account_number'] ?? '201025429005');
        $this->addTableRow($table1, 'Account Type', $annexure1BankDetails['account_type'] ?? 'Current');
        $this->addTableRow($table1, 'IFSC Code', $annexure1BankDetails['ifsc_code'] ?? 'INDB0000662');

        // Annexure II
        $section->addPageBreak();
        $section->addText($this->esc('Annexure II – Security Deposit Refund Account Information'), $headerStyle, ['alignment' => Jc::CENTER]);
        $section->addText($this->esc('RTGS / NEFT / E-Payment Form'), ['size' => 12, 'bold' => true], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);
        $section->addText($this->esc('Sub: Authorization to effect payments through RTGS/NEFT/Electronic Payment Platform'), ['italic' => true], $paraStyle);
        $section->addText($this->esc("Name of the Licensee: {$tenantName}"), $boldStyle, $paraStyle);

        $table2 = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80]);
        $this->addTableRow($table2, 'Beneficiary Name', $tenantBankDetails['account_holder_name'] ?? $tenantName);
        $this->addTableRow($table2, 'Name of the Bank', $tenantBankDetails['bank_name'] ?? '_______________________');
        $this->addTableRow($table2, 'Address / Branch', $tenantBankDetails['bank_address'] ?? '_______________________');
        $this->addTableRow($table2, 'Bank Account No', $tenantBankDetails['account_number'] ?? '_______________________');
        $this->addTableRow($table2, 'Account Type', $tenantBankDetails['account_type'] ?? 'Saving');
        $this->addTableRow($table2, 'IFSC Code', $tenantBankDetails['ifsc_code'] ?? '_______________________');
        $this->addTableRow($table2, 'PAN Number', $tenantBankDetails['pan_number'] ?? $tenant->individual?->pan_number ?? '_______________________');

        // Annexure III
        $section->addPageBreak();
        $section->addText($this->esc('Annexure III – Furnishing / Electrical / Electronic Items Information'), $headerStyle, ['alignment' => Jc::CENTER]);
        $auditNumber = $agreement->audit ? $agreement->audit->audit_number : 'N/A';
        $section->addText($this->esc("(Pulled from Move-In Audit Reference: {$auditNumber})"), ['size' => 10, 'italic' => true, 'color' => '555555'], ['alignment' => Jc::CENTER, 'spaceAfter' => 100]);
        $section->addText($this->esc('Sub: List of rooms, inventory, furnishing, and electrical/electronic items equipped in the licensed premise, organized by room:'), ['italic' => true], $paraStyle);

        $roomGroupedItems = app(TenancyAgreementPdfService::class)->organizeAuditByRoom($agreement);

        if (! empty($roomGroupedItems)) {
            foreach ($roomGroupedItems as $group) {
                $roomTitle = $group['room_name'];
                if (! empty($group['room_item'])) {
                    $cond = $group['room_item']->condition->value ?? $group['room_item']->condition ?? 'Good';
                    $roomTitle .= " (Room Condition: {$cond})";
                }
                $section->addText($this->esc($roomTitle), ['bold' => true, 'size' => 11, 'color' => '1E3A8A'], ['spaceBefore' => 140, 'spaceAfter' => 60]);

                if (! empty($group['items'])) {
                    $table3 = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 60]);
                    $table3->addRow();
                    $table3->addCell(800)->addText('#', ['bold' => true]);
                    $table3->addCell(4200)->addText('Item / Fitting Name', ['bold' => true]);
                    $table3->addCell(1800)->addText('Condition', ['bold' => true]);
                    $table3->addCell(2800)->addText('Notes / Remarks', ['bold' => true]);

                    foreach ($group['items'] as $idx => $item) {
                        $table3->addRow();
                        $table3->addCell(800)->addText((string) ($idx + 1));
                        $table3->addCell(4200)->addText($this->esc($item->display_name ?? $item->name ?? ''));
                        $table3->addCell(1800)->addText($this->esc($item->condition->value ?? $item->condition ?? 'Good'));
                        $table3->addCell(2800)->addText($this->esc($item->remarks ?? $item->snapshot_data['notes'] ?? '-'));
                    }
                } else {
                    $section->addText($this->esc('No specific inventory or fittings listed for this room.'), ['italic' => true]);
                }
            }
        } elseif ($agreement->audit && $agreement->audit->categories && $agreement->audit->categories->isNotEmpty()) {
            foreach ($agreement->audit->categories as $category) {
                $section->addText($this->esc($category->name), ['bold' => true, 'size' => 11, 'color' => '1E3A8A'], ['spaceBefore' => 140, 'spaceAfter' => 60]);
                if ($category->items && $category->items->isNotEmpty()) {
                    $table3 = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 60]);
                    $table3->addRow();
                    $table3->addCell(800)->addText('#', ['bold' => true]);
                    $table3->addCell(4200)->addText('Item Name', ['bold' => true]);
                    $table3->addCell(1800)->addText('Condition', ['bold' => true]);
                    $table3->addCell(2800)->addText('Notes / Remarks', ['bold' => true]);

                    foreach ($category->items as $idx => $item) {
                        $table3->addRow();
                        $table3->addCell(800)->addText((string) ($idx + 1));
                        $table3->addCell(4200)->addText($this->esc($item->name ?? ''));
                        $table3->addCell(1800)->addText($this->esc($item->condition->value ?? $item->condition ?? 'Good'));
                        $table3->addCell(2800)->addText($this->esc($item->remarks ?? $item->snapshot_data['notes'] ?? '-'));
                    }
                } else {
                    $section->addText($this->esc('No specific items listed under this category.'), ['italic' => true]);
                }
            }
        } else {
            $section->addText($this->esc('No audit reference selected or no inventory items recorded.'), ['italic' => true, 'color' => '777777'], ['alignment' => Jc::CENTER, 'spaceBefore' => 120]);
        }

        $filename = 'Tenancy_Agreement_Draft_'.($agreement->code ?? $agreement->id).'.docx';
        $tempPath = sys_get_temp_dir().'/'.$filename;

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);

        return file_get_contents($tempPath);
    }

    public function saveDraftDocx(TenancyAgreement $agreement): void
    {
        $binary = $this->generateDocx($agreement);
        $filename = 'Tenancy_Agreement_Draft_'.($agreement->code ?? $agreement->id).'.docx';

        $tempPath = sys_get_temp_dir().'/'.$filename;
        file_put_contents($tempPath, $binary);

        $agreement->clearMediaCollection('draft_word');
        $agreement->addMedia($tempPath)->toMediaCollection('draft_word');
    }

    private function addTableRow($table, string $label, string $value): void
    {
        $table->addRow();
        $table->addCell(4000)->addText($this->esc($label), ['bold' => true]);
        $table->addCell(5000)->addText($this->esc($value));
    }

    private function esc(?string $text): string
    {
        if ($text === null) {
            return '';
        }

        return htmlspecialchars((string) $text, ENT_XML1, 'UTF-8');
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
