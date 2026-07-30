<?php

namespace App\Domain\Agreement\Services;

use App\Domain\Agreement\Models\TenancyAgreement;
use Barryvdh\DomPDF\Facade\Pdf;
use NumberFormatter;

class TenancyAgreementPdfService
{
    public function generatePdf(TenancyAgreement $agreement): string
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

        $mou = $agreement->property?->mous()?->latest()->first();
        $annexure1BankDetails = [
            'beneficiary_name' => $mou?->bank_details['beneficiary_name'] ?? $mou?->bank_details['account_holder_name'] ?? 'ASSAM ALAY',
            'bank_name' => $mou?->bank_details['bank_name'] ?? 'IndusInd Bank',
            'bank_address' => $mou?->bank_details['bank_address'] ?? 'Beltola, Guwahati',
            'account_number' => $mou?->bank_details['account_number'] ?? '201025429005',
            'account_type' => $mou?->bank_details['account_type'] ?? 'Current',
            'ifsc_code' => $mou?->bank_details['ifsc_code'] ?? 'INDB0000662',
        ];

        $auditCategories = $agreement->audit ? $agreement->audit->categories : collect();

        $pdf = Pdf::loadView('pdf.tenancy_agreement', [
            'agreement' => $agreement,
            'property' => $agreement->property,
            'owner' => $owner,
            'tenant' => $tenant,
            'ownerAddress' => $ownerAddress,
            'tenantAddress' => $tenantAddress,
            'propertyAddress' => $propertyAddress,
            'rentInWords' => $rentInWords,
            'depositInWords' => $depositInWords,
            'annexure1BankDetails' => $annexure1BankDetails,
            'tenantBankDetails' => $tenantBankDetails,
            'audit' => $agreement->audit,
            'auditCategories' => $auditCategories,
        ]);

        return $pdf->output();
    }

    public function saveDraftPdf(TenancyAgreement $agreement): void
    {
        $binary = $this->generatePdf($agreement);
        $filename = 'Tenancy_Agreement_Draft_' . ($agreement->code ?? $agreement->id) . '.pdf';
        
        $tempPath = sys_get_temp_dir() . '/' . $filename;
        file_put_contents($tempPath, $binary);

        $agreement->clearMediaCollection('draft_pdf');
        $agreement->addMedia($tempPath)->toMediaCollection('draft_pdf');
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
