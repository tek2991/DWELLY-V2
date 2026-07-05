<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Opportunity\Models\FinancialModel;

class FinancialModelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $models = [
            [
                'code' => 'FIXED_RENT',
                'name' => 'Fixed Rent Guarantee',
                'description' => 'Dwelly pays a fixed monthly rent to the owner, regardless of occupancy. Dwelly retains all upside from renting out to tenants.',
            ],
            [
                'code' => 'REVENUE_SHARE',
                'name' => 'Revenue Share',
                'description' => 'Dwelly and the property owner share the actual rental income generated from the tenants based on an agreed percentage split.',
            ],
            [
                'code' => 'PROPERTY_MANAGEMENT',
                'name' => 'Property Management (Fee-Based)',
                'description' => 'The owner receives full rent from tenants and pays Dwelly a flat fee or percentage for management services.',
            ],
            [
                'code' => 'HYBRID',
                'name' => 'Hybrid Model',
                'description' => 'A mix of fixed rent and revenue share (e.g., minimum guaranteed rent plus a share of revenue above a certain threshold).',
            ],
        ];

        foreach ($models as $modelData) {
            FinancialModel::firstOrCreate(
                ['code' => $modelData['code']],
                [
                    'name' => $modelData['name'],
                    'description' => $modelData['description'],
                    'is_active' => true,
                ]
            );
        }
    }
}
