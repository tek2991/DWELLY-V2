<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Domain\Opportunity\Models\OpportunitySource;

class OpportunitySourceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hierarchy = [
            'Digital' => [
                'Website',
                'WhatsApp',
                'Facebook',
                'Instagram',
                'Google',
            ],
            'Referral' => [
                'Broker',
                'Existing Owner',
                'Existing Tenant',
                'Employee Referral',
                'Referral Partner',
            ],
            'Direct' => [
                'Walk-in',
                'Cold Call',
                'Owner Direct',
            ],
            'Partner' => [
                'Builder',
                'Developer',
            ],
            'Portals' => [
                'MagicBricks',
                '99acres',
            ],
            'Internal' => [
                'CRM',
                'Manual Entry',
                'Migration',
            ],
        ];

        foreach ($hierarchy as $parentName => $children) {
            $parent = OpportunitySource::firstOrCreate(
                ['name' => $parentName],
                ['is_active' => true, 'parent_id' => null]
            );

            foreach ($children as $childName) {
                OpportunitySource::firstOrCreate(
                    ['name' => $childName],
                    ['is_active' => true, 'parent_id' => $parent->id]
                );
            }
        }
    }
}
