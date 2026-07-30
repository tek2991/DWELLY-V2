<?php

namespace App\Domain\Agreement\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use NumberFormatter;

class TenancyAgreementDocxService
{
    public function generateDocx(TenancyAgreement $agreement): string
    {
        $agreement->load([
            'property',
            'property.owner',
            'audit',
            'audit.categories.items',
            'roles.party',
            'roles.party.individual',
            'roles.party.addresses',
        ]);

        $owner = $agreement->property?->owner;
        $primaryRole = $agreement->roles->where('is_primary', true)->first();
        $tenant = $primaryRole?->party;

        $ownerAddress = $owner?->addresses->where('is_primary', true)->first()?->address_line_1 
            ?? $owner?->addresses->first()?->address_line_1 
            ?? '';

        $tenantAddress = $tenant?->addresses->where('is_primary', true)->first()?->address_line_1 
            ?? $tenant?->addresses->first()?->address_line_1 
            ?? '';

        $propertyAddress = $agreement->property?->address 
            ?? $agreement->property?->location 
            ?? '';

        $rentInWords = $this->numberToWords((int) $agreement->rent_amount);
        $depositInWords = $this->numberToWords((int) $agreement->security_deposit);
        $tenantBankDetails = $agreement->tenant_bank_details ?? [];

        $phpWord = new PhpWord();
        $section = $phpWord->addSection([
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1200,
            'marginRight' => 1200,
        ]);

        // Styles
        $headerStyle = ['size' => 16, 'bold' => true, 'align' => 'center'];
        $titleStyle = ['size' => 14, 'bold' => true, 'underline' => 'single'];
        $boldStyle = ['bold' => true];
        $paraStyle = ['spaceAfter' => 120];

        // Title
        $section->addText('LEAVE AND LICENSE AGREEMENT', $headerStyle, ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);
        $section->addTextBreak(1);

        // Intro
        $createdDate = $agreement->created_at ? $agreement->created_at->format('jS \d\a\y \o\f F Y') : date('jS \d\a\y \o\f F Y');
        $section->addText("This Leave and License Agreement (“Agreement”) is made and executed in Guwahati on this {$createdDate} BETWEEN", [], $paraStyle);

        $ownerName = $owner->display_name ?? 'Property Owner';
        $parentName = !empty($owner->individual?->parent_name) ? " S/o or D/o {$owner->individual->parent_name}," : '';
        $section->addText("{$ownerName},{$parentName} resident of {$ownerAddress} (hereinafter referred to as the “Licensor”)", $boldStyle, $paraStyle);

        $section->addText('AND', ['bold' => true, 'align' => 'center'], ['alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER]);

        $tenantName = $tenant->display_name ?? 'Tenant Name';
        $tParent = !empty($tenant->individual?->parent_name) ? " C/o {$tenant->individual->parent_name}," : '';
        $tAadhaar = !empty($tenant->individual?->aadhaar_number) ? " with Aadhaar number {$tenant->individual->aadhaar_number}," : '';
        $tPhone = $tenant->phone ?? '';
        $section->addText("{$tenantName},{$tParent} resident of {$tenantAddress}{$tAadhaar} Phone: {$tPhone} (hereinafter referred to as the “Licensee”).", $boldStyle, $paraStyle);

        $propName = $agreement->property?->building_name ?? $agreement->property?->name ?? '';
        $section->addText("AND WHEREAS the ‘Licensor’ is the absolute owner in full possession of the constructed structure described as {$propName}, {$propertyAddress} (hereinafter referred to as the “Licensed Premises”).", [], $paraStyle);

        $startDate = $agreement->start_date ? $agreement->start_date->format('jS F Y') : '________';
        $endDate = $agreement->end_date ? $agreement->end_date->format('jS F Y') : '________';
        $section->addText("AND WHEREAS the Licensee herein is in need of the above mentioned premises for residential use for a period commencing from {$startDate} and ending on {$endDate}.", [], $paraStyle);

        $section->addText('Now therefore it is hereby agreed to, declared and recorded by and between the parties hereto as follows:-', $boldStyle, $paraStyle);

        // Sections
        $section->addText('Commencement and Period:', $titleStyle, $paraStyle);
        $section->addListItem("This Agreement shall commence from {$startDate} and ending on {$endDate}. If the Licensee vacates within the first Six (6) months, the entire security deposit will be charged as cancellation fee.", 0, null, null, $paraStyle);
        $section->addListItem('The agreement can be renewed after the end of each tenure on mutually agreed terms.', 0, null, null, $paraStyle);

        $section->addText('Rent & Deposit:', $titleStyle, $paraStyle);
        $rentAmt = number_format($agreement->rent_amount ?? 0, 2);
        $depAmt = number_format($agreement->security_deposit ?? 0, 2);
        $depNotes = !empty($agreement->security_deposit_notes) ? " ({$agreement->security_deposit_notes})" : '';
        $section->addListItem("The Licensee shall pay a license fee of Rupees {$rentInWords} (INR {$rentAmt}) per month directly to the bank account mentioned in Annexure - I.", 0, null, null, $paraStyle);
        $section->addListItem("The License amount shall be payable within the first five days of the concerned month.", 0, null, null, $paraStyle);
        $section->addListItem("The two months security deposit of Rupees {$depositInWords} (INR {$depAmt}){$depNotes} shall be paid before moving-in and returned within 7 to 10 bank working days of vacating after key handover.", 0, null, null, $paraStyle);
        $section->addListItem("The keys will only be handed over once the entire security deposit has been paid to Annexure - I.", 0, null, null, $paraStyle);

        $section->addText('Electricity & APDCL Meter:', $titleStyle, $paraStyle);
        $apdcl = $agreement->apdcl_consumer_id ?? '___________';
        $section->addListItem("The APDCL prepaid smart meter consumer ID is {$apdcl}. Electricity bills shall be paid timely by Licensee.", 0, null, null, $paraStyle);

        $section->addText('Furniture/Fittings & Appliances:', $titleStyle, $paraStyle);
        $section->addListItem('The Licensed premises shall be kept in the best condition possible. Items listed in Annexure - III shall be returned in good condition as documented in Move-In Audit.', 0, null, null, $paraStyle);

        $section->addPageBreak();

        // Annexure I
        $section->addText('Annexure I – Rent deposit Account Information', $titleStyle, $paraStyle);
        $table1 = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80]);
        $this->addTableRow($table1, 'Beneficiary Name', 'ASSAM ALAY');
        $this->addTableRow($table1, 'Name of the Bank', 'IndusInd Bank');
        $this->addTableRow($table1, 'Address of the Bank', 'Beltola, Guwahati');
        $this->addTableRow($table1, 'Bank Account No', '201025429005');
        $this->addTableRow($table1, 'Account Type', 'Current');
        $this->addTableRow($table1, 'IFSC Code', 'INDB0000662');

        $section->addTextBreak(1);

        // Annexure II
        $section->addText('Annexure II – Security Deposit Refund Account Information', $titleStyle, $paraStyle);
        $table2 = $section->addTable(['borderSize' => 6, 'borderColor' => '999999', 'cellMargin' => 80]);
        $this->addTableRow($table2, 'Beneficiary Name', $tenantBankDetails['account_holder_name'] ?? $tenantName);
        $this->addTableRow($table2, 'Name of the Bank', $tenantBankDetails['bank_name'] ?? '');
        $this->addTableRow($table2, 'Address / Branch', $tenantBankDetails['bank_address'] ?? '');
        $this->addTableRow($table2, 'Bank Account No', $tenantBankDetails['account_number'] ?? '');
        $this->addTableRow($table2, 'Account Type', $tenantBankDetails['account_type'] ?? 'Saving');
        $this->addTableRow($table2, 'IFSC Code', $tenantBankDetails['ifsc_code'] ?? '');
        $this->addTableRow($table2, 'PAN Number', $tenantBankDetails['pan_number'] ?? $tenant->individual?->pan_number ?? '');

        $section->addPageBreak();

        // Annexure III
        $section->addText('Annexure III – Furnishing / Electrical / Electronic Items Information', $titleStyle, $paraStyle);
        $auditNumber = $agreement->audit ? $agreement->audit->audit_number : 'N/A';
        $section->addText("List of items equipped in licensed premise (Ref: {$auditNumber}):", ['italic' => true], $paraStyle);

        if ($agreement->audit && $agreement->audit->categories) {
            foreach ($agreement->audit->categories as $category) {
                $section->addText($category->name, ['bold' => true, 'size' => 12], ['spaceBefore' => 120, 'spaceAfter' => 60]);
                $table3 = $section->addTable(['borderSize' => 6, 'borderColor' => 'CCCCCC', 'cellMargin' => 60]);
                $table3->addRow();
                $table3->addCell(800)->addText('#', ['bold' => true]);
                $table3->addCell(3500)->addText('Item Name', ['bold' => true]);
                $table3->addCell(2000)->addText('Condition', ['bold' => true]);
                $table3->addCell(3500)->addText('Notes', ['bold' => true]);

                foreach ($category->items as $idx => $item) {
                    $table3->addRow();
                    $table3->addCell(800)->addText($idx + 1);
                    $table3->addCell(3500)->addText($item->name ?? '');
                    $table3->addCell(2000)->addText($item->condition->value ?? $item->condition ?? 'Good');
                    $table3->addCell(3500)->addText($item->remarks ?? $item->snapshot_data['notes'] ?? '-');
                }
            }
        } else {
            $section->addText('No audit items linked.', ['italic' => true]);
        }

        $filename = 'Tenancy_Agreement_Draft_' . ($agreement->code ?? $agreement->id) . '.docx';
        $tempPath = sys_get_temp_dir() . '/' . $filename;

        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);

        return file_get_contents($tempPath);
    }

    public function saveDraftDocx(TenancyAgreement $agreement): void
    {
        $binary = $this->generateDocx($agreement);
        $filename = 'Tenancy_Agreement_Draft_' . ($agreement->code ?? $agreement->id) . '.docx';
        
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($tempPath, $binary);

        $agreement->clearMediaCollection('draft_word');
        $agreement->addMedia($tempPath)->toMediaCollection('draft_word');
    }

    private function addTableRow($table, string $label, string $value): void
    {
        $table->addRow();
        $table->addCell(4000)->addText($label, ['bold' => true]);
        $table->addCell(5000)->addText($value);
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
