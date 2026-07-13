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
        // Clear foreign keys first so we can replace them
        \Illuminate\Support\Facades\DB::table('opportunities')->update(['expected_financial_model_id' => null]);
        
        // Clear old models since we are replacing them
        FinancialModel::query()->delete();

        $models = [
            [
                'code' => 'RENT_SHARE',
                'name' => 'Rent share',
                'description' => 'Monthly % of rent',
                'fee_collection' => 'Auto-deducted from monthly owner payout',
            ],
            [
                'code' => 'ANNUAL_SUBSCRIPTION',
                'name' => 'Annual subscription',
                'description' => "One month's rent",
                'fee_collection' => 'Charged at agreement signing or renewal',
            ],
        ];

        foreach ($models as $modelData) {
            FinancialModel::firstOrCreate(
                ['code' => $modelData['code']],
                [
                    'name' => $modelData['name'],
                    'description' => $modelData['description'],
                    'fee_collection' => $modelData['fee_collection'],
                    'is_active' => true,
                ]
            );
        }
    }
}
