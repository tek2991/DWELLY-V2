<?php

use App\Domain\Shared\Enums\KycRequirementLevel;
use App\Domain\Shared\Models\KycDocumentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('kyc_document_types') && KycDocumentType::count() > 0) {
            KycDocumentType::updateOrCreate(
                ['code' => 'passport_photo'],
                [
                    'name' => 'Passport Photo',
                    'description' => 'Passport-sized photograph of the tenant',
                    'icon' => 'heroicon-m-user-circle',
                    'has_number' => false,
                    'number_label' => null,
                    'number_regex' => null,
                    'mou_owner_requirement' => KycRequirementLevel::DISABLED,
                    'mou_signatory_requirement' => KycRequirementLevel::DISABLED,
                    'tenancy_primary_requirement' => KycRequirementLevel::MANDATORY,
                    'tenancy_secondary_requirement' => KycRequirementLevel::MANDATORY,
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('kyc_document_types')) {
            KycDocumentType::where('code', 'passport_photo')->delete();
        }
    }
};
