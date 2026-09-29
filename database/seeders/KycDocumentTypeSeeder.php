<?php

namespace Database\Seeders;

use App\Domain\Shared\Enums\KycRequirementLevel;
use App\Domain\Shared\Models\KycDocumentType;
use Illuminate\Database\Seeder;

class KycDocumentTypeSeeder extends Seeder
{
    public function run(): void
    {
        $documents = [
            [
                'name' => 'Aadhaar Card',
                'code' => 'aadhaar',
                'description' => 'Front & Back image or PDF of Government Aadhaar card',
                'icon' => 'heroicon-m-identification',
                'has_number' => true,
                'number_label' => 'Aadhaar Number',
                'mou_owner_requirement' => KycRequirementLevel::MANDATORY,
                'mou_signatory_requirement' => KycRequirementLevel::MANDATORY,
                'tenancy_primary_requirement' => KycRequirementLevel::MANDATORY,
                'tenancy_secondary_requirement' => KycRequirementLevel::OPTIONAL,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'name' => 'PAN Card',
                'code' => 'pan',
                'description' => 'Clear image or PDF of Income Tax PAN card',
                'icon' => 'heroicon-m-credit-card',
                'has_number' => true,
                'number_label' => 'PAN Number',
                'mou_owner_requirement' => KycRequirementLevel::MANDATORY,
                'mou_signatory_requirement' => KycRequirementLevel::MANDATORY,
                'tenancy_primary_requirement' => KycRequirementLevel::OPTIONAL,
                'tenancy_secondary_requirement' => KycRequirementLevel::OPTIONAL,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Cancelled Cheque',
                'code' => 'cancelled_cheque',
                'description' => 'Cancelled cheque leaf or bank passbook copy for payout verification',
                'icon' => 'heroicon-m-banknotes',
                'has_number' => false,
                'number_label' => null,
                'mou_owner_requirement' => KycRequirementLevel::MANDATORY,
                'mou_signatory_requirement' => KycRequirementLevel::DISABLED,
                'tenancy_primary_requirement' => KycRequirementLevel::OPTIONAL,
                'tenancy_secondary_requirement' => KycRequirementLevel::DISABLED,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'name' => 'Electricity Bill',
                'code' => 'electricity_bill',
                'description' => 'Latest electricity / utility bill copy for premises verification',
                'icon' => 'heroicon-m-bolt',
                'has_number' => true,
                'number_label' => 'Electricity Consumer / CA Number',
                'mou_owner_requirement' => KycRequirementLevel::MANDATORY,
                'mou_signatory_requirement' => KycRequirementLevel::DISABLED,
                'tenancy_primary_requirement' => KycRequirementLevel::DISABLED,
                'tenancy_secondary_requirement' => KycRequirementLevel::DISABLED,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'name' => 'Power of Attorney (POA)',
                'code' => 'power_of_attorney',
                'description' => 'Registered Power of Attorney deed or formal authorization letter',
                'icon' => 'heroicon-m-scale',
                'has_number' => false,
                'number_label' => null,
                'mou_owner_requirement' => KycRequirementLevel::DISABLED,
                'mou_signatory_requirement' => KycRequirementLevel::MANDATORY,
                'tenancy_primary_requirement' => KycRequirementLevel::DISABLED,
                'tenancy_secondary_requirement' => KycRequirementLevel::DISABLED,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'name' => 'Passport',
                'code' => 'passport',
                'description' => 'First and last page copy of Passport',
                'icon' => 'heroicon-m-globe-alt',
                'has_number' => true,
                'number_label' => 'Passport Number',
                'mou_owner_requirement' => KycRequirementLevel::OPTIONAL,
                'mou_signatory_requirement' => KycRequirementLevel::OPTIONAL,
                'tenancy_primary_requirement' => KycRequirementLevel::OPTIONAL,
                'tenancy_secondary_requirement' => KycRequirementLevel::OPTIONAL,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'name' => 'Voter ID',
                'code' => 'voter_id',
                'description' => 'Election commission Voter ID card',
                'icon' => 'heroicon-m-identification',
                'has_number' => true,
                'number_label' => 'Voter ID Number',
                'mou_owner_requirement' => KycRequirementLevel::OPTIONAL,
                'mou_signatory_requirement' => KycRequirementLevel::OPTIONAL,
                'tenancy_primary_requirement' => KycRequirementLevel::OPTIONAL,
                'tenancy_secondary_requirement' => KycRequirementLevel::OPTIONAL,
                'is_active' => true,
                'sort_order' => 7,
            ],
            [
                'name' => 'Driving License',
                'code' => 'driving_license',
                'description' => 'Valid Driving License issued by RTO',
                'icon' => 'heroicon-m-truck',
                'has_number' => true,
                'number_label' => 'Driving License Number',
                'mou_owner_requirement' => KycRequirementLevel::OPTIONAL,
                'mou_signatory_requirement' => KycRequirementLevel::OPTIONAL,
                'tenancy_primary_requirement' => KycRequirementLevel::OPTIONAL,
                'tenancy_secondary_requirement' => KycRequirementLevel::OPTIONAL,
                'is_active' => true,
                'sort_order' => 8,
            ],
        ];

        foreach ($documents as $doc) {
            KycDocumentType::updateOrCreate(
                ['code' => $doc['code']],
                $doc
            );
        }
    }
}
