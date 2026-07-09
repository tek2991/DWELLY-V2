<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PropertyOnboardingTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        // 1. Create Template
        $templateId = (string) Str::ulid();
        DB::table('workflow_templates')->insert([
            'id' => $templateId,
            'name' => 'Standard Property Onboarding V1',
            'type' => 'Property Onboarding',
            'version' => 1,
            'is_active' => true,
            'estimated_duration_days' => 14,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 2. Create a generic Work Package
        $workPackageId = (string) Str::ulid();
        DB::table('workflow_work_packages')->insert([
            'id' => $workPackageId,
            'workflow_template_id' => $templateId,
            'name' => 'Property Setup deliverables',
            'is_mandatory' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // 3. Define structure
        $structure = [
            'Phase 1: Initial Verification' => [
                'Document Check' => [
                    'Verify Owner KYC Documents',
                    'Verify Property Title Deed',
                ],
            ],
            'Phase 2: Property Setup & Data Collection' => [
                'Basic Details' => [
                    'Update exact Address and Geographic coordinates',
                    'Define Room Configuration (Bedrooms, Bathrooms, Balconies)',
                ],
                'Amenities & Establishments' => [
                    'Map Property Amenities',
                    'Map Nearby Establishments and Landmarks',
                ],
                'Inventory Setup' => [
                    'Log all Furniture, Fixtures, and Appliances',
                ],
            ],
            'Phase 3: Financial Configuration' => [
                'Pricing & Utilities' => [
                    'Set Base Rent and Security Deposit',
                    'Configure Utility Billing (Electricity, Water)',
                ],
            ],
        ];

        $phaseOrder = 1;
        $stageOrder = 1;

        foreach ($structure as $phaseName => $stages) {
            $phaseId = (string) Str::ulid();
            DB::table('workflow_phases')->insert([
                'id' => $phaseId,
                'workflow_template_id' => $templateId,
                'name' => $phaseName,
                'order' => $phaseOrder++,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($stages as $stageName => $tasks) {
                $stageId = (string) Str::ulid();
                DB::table('workflow_stages')->insert([
                    'id' => $stageId,
                    'workflow_template_id' => $templateId,
                    'phase_id' => $phaseId,
                    'name' => $stageName,
                    'order' => $stageOrder++,
                    'is_mandatory' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($tasks as $taskTitle) {
                    $taskId = (string) Str::ulid();
                    DB::table('workflow_task_templates')->insert([
                        'id' => $taskId,
                        'workflow_template_id' => $templateId,
                        'work_package_id' => $workPackageId,
                        'stage_id' => $stageId,
                        'title' => $taskTitle,
                        'priority' => 'medium',
                        'is_mandatory' => true,
                        'weight' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // 4. Create Deliverables
        $deliverables = [
            'Signed Management Agreement',
            'Property Photography',
            'Verified Owner KYC',
            'Physical Keys Handed Over'
        ];

        foreach ($deliverables as $deliverableName) {
            DB::table('workflow_deliverables')->insert([
                'id' => (string) Str::ulid(),
                'workflow_template_id' => $templateId,
                'work_package_id' => $workPackageId,
                'name' => $deliverableName,
                'provider_key' => 'internal',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
}
