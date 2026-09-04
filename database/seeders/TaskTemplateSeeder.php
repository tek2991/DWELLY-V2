<?php

namespace Database\Seeders;

use App\Domain\Task\Enums\TaskCategory;
use App\Domain\Task\Enums\TaskPriority;
use App\Domain\Task\Models\TaskTemplate;
use Illuminate\Database\Seeder;

class TaskTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'code' => 'POLICE_VERIFICATION',
                'name' => 'Tenant Police Verification',
                'category' => TaskCategory::COMPLIANCE,
                'default_priority' => TaskPriority::HIGH,
                'default_sla_hours' => 168, // 7 days
                'description' => 'Complete tenant police verification submission and upload stamped acknowledgment challan.',
                'items' => [
                    ['title' => 'Collect Tenant Aadhaar, Photo & Permanent Address Proof', 'is_mandatory' => true, 'sort_order' => 1],
                    ['title' => 'Fill Police Verification Form & Attach Tenancy Agreement copy', 'is_mandatory' => true, 'sort_order' => 2],
                    ['title' => 'Submit to Local Police Station & Collect Stamped Challan', 'is_mandatory' => true, 'sort_order' => 3],
                    ['title' => 'Upload Stamped Challan to Task Completion Proofs', 'is_mandatory' => true, 'sort_order' => 4],
                ],
            ],
            [
                'code' => 'VACANT_PROPERTY_MEDIA',
                'name' => 'Install To-Let Board & Capture Listing Media',
                'category' => TaskCategory::FIELD_WORK,
                'default_priority' => TaskPriority::HIGH,
                'default_sla_hours' => 72, // 3 days
                'description' => 'Visit property, install Dwelly To-Let signboard, and capture updated marketing photos for prospective tenant listing.',
                'items' => [
                    ['title' => 'Install Dwelly To-Let Board with contact number', 'is_mandatory' => true, 'sort_order' => 1],
                    ['title' => 'Capture high-resolution photos of living room, bedrooms, kitchen, bathrooms', 'is_mandatory' => true, 'sort_order' => 2],
                    ['title' => 'Verify master keys are in designated lockbox or office vault', 'is_mandatory' => true, 'sort_order' => 3],
                    ['title' => 'Verify flat is clean, aired, and ready for prospective tenant visits', 'is_mandatory' => true, 'sort_order' => 4],
                ],
            ],
            [
                'code' => 'MOVE_IN_CHECKIN',
                'name' => '7-Day Post Move-In Check-In Call',
                'category' => TaskCategory::LIFECYCLE,
                'default_priority' => TaskPriority::MEDIUM,
                'default_sla_hours' => 168, // 7 days
                'description' => 'Call tenant to verify move-in comfort, appliance functioning, and resolve settle-in questions.',
                'items' => [
                    ['title' => 'Call Tenant and verify keys, remotes & society access cards', 'is_mandatory' => true, 'sort_order' => 1],
                    ['title' => 'Confirm electricity submeter readings match move-in audit', 'is_mandatory' => true, 'sort_order' => 2],
                    ['title' => 'Check if any minor appliance or fixture issue was discovered', 'is_mandatory' => false, 'sort_order' => 3],
                    ['title' => 'Log call feedback and update resolution notes', 'is_mandatory' => true, 'sort_order' => 4],
                ],
            ],
            [
                'code' => 'LEASE_RENEWAL_OUTREACH',
                'name' => '60-Day Lease Renewal Outreach',
                'category' => TaskCategory::LIFECYCLE,
                'default_priority' => TaskPriority::MEDIUM,
                'default_sla_hours' => 240, // 10 days
                'description' => 'Proactively engage owner and tenant 60 days before tenancy expiry to confirm renewal or notice intention.',
                'items' => [
                    ['title' => 'Contact property owner to confirm renewal intent and revised rent expectations', 'is_mandatory' => true, 'sort_order' => 1],
                    ['title' => 'Contact tenant to discuss renewal and tenure extension', 'is_mandatory' => true, 'sort_order' => 2],
                    ['title' => 'Confirm agreed terms and prepare draft agreement addendum', 'is_mandatory' => true, 'sort_order' => 3],
                ],
            ],
            [
                'code' => 'ROUTINE_HEALTH_CHECK',
                'name' => 'Quarterly Routine Property Health Check',
                'category' => TaskCategory::FIELD_WORK,
                'default_priority' => TaskPriority::MEDIUM,
                'default_sla_hours' => 120, // 5 days
                'description' => 'Quarterly preventive site visit to inspect plumbing, electrical fittings, and structural condition.',
                'items' => [
                    ['title' => 'Check plumbing, taps, drains, and water pressure across all bathrooms', 'is_mandatory' => true, 'sort_order' => 1],
                    ['title' => 'Inspect MCB switchboard, electrical points, and major appliances', 'is_mandatory' => true, 'sort_order' => 2],
                    ['title' => 'Inspect walls/ceilings for moisture, seepage, or cracks', 'is_mandatory' => true, 'sort_order' => 3],
                    ['title' => 'Verify balcony/window grills and locking mechanisms', 'is_mandatory' => true, 'sort_order' => 4],
                    ['title' => 'Capture 5+ proof inspection photos', 'is_mandatory' => true, 'sort_order' => 5],
                ],
            ],
            [
                'code' => 'PRE_MONSOON_AUDIT',
                'name' => 'Pre-Monsoon Drainage & Roof Audit',
                'category' => TaskCategory::FIELD_WORK,
                'default_priority' => TaskPriority::HIGH,
                'default_sla_hours' => 168, // 7 days
                'description' => 'Seasonal inspection before monsoon to prevent rainwater seepage and drain blockages.',
                'items' => [
                    ['title' => 'Inspect terrace drains and rainwater downpipes for blockages', 'is_mandatory' => true, 'sort_order' => 1],
                    ['title' => 'Inspect window sill seals and balcony water outlets', 'is_mandatory' => true, 'sort_order' => 2],
                    ['title' => 'Verify external wiring and outdoor socket waterproofing', 'is_mandatory' => true, 'sort_order' => 3],
                ],
            ],
        ];

        foreach ($templates as $data) {
            $items = $data['items'];
            unset($data['items']);

            $template = TaskTemplate::updateOrCreate(
                ['code' => $data['code']],
                $data
            );

            // Sync items
            $template->items()->delete();
            foreach ($items as $itemData) {
                $template->items()->create($itemData);
            }
        }
    }
}
