<?php

namespace Tek2991\Accounting\Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Tek2991\Accounting\Models\FiscalPeriod;
use Tek2991\Accounting\Models\Organization;
use Tek2991\Accounting\Models\State;
use Tek2991\Accounting\Models\GstRegistration;
use App\Models\Branch;

class DemoOrganizationSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Organization
        $org = Organization::current();
        $org->update(['tax_regime' => \Tek2991\Accounting\Enums\TaxRegimeType::IndiaGst]);

        $assam = State::where('name', 'Assam')->first();
        $maharashtra = State::where('name', 'Maharashtra')->first();
        $karnataka = State::where('name', 'Karnataka')->first();

        $gstRegistration = GstRegistration::firstOrCreate([
            'gstin' => '18AAAAA0000A1Z5',
        ], [
            'legal_name' => 'Dwelly India Pvt Ltd',
            'state_id' => $assam?->id,
            'is_default' => true,
        ]);

        $branch = Branch::firstOrCreate([
            'code' => 'GHY',
        ], [
            'organization_id' => $org->id,
            'gst_registration_id' => $gstRegistration->id,
            'name' => 'Guwahati Head Office',
            'city' => 'Guwahati',
            'state_id' => $assam?->id,
            'is_active' => true,
        ]);

        $bangaloreBranch = Branch::firstOrCreate([
            'code' => 'BLR',
        ], [
            'organization_id' => $org->id,
            'gst_registration_id' => $gstRegistration->id,
            'name' => 'Bangalore Branch',
            'city' => 'Bangalore',
            'state_id' => $karnataka?->id ?? $maharashtra?->id,
            'is_active' => true,
        ]);
        
        $branchContext = app(\Tek2991\Accounting\Services\BranchContext::class);
        $branchContext->set($branch);

        // 2. Fiscal Periods (Previous, Current)
        $startMonth = \Tek2991\Accounting\Facades\Accounting::getFiscalYearStart();
        $currentYear = now()->year;
        $startYear = now()->month >= $startMonth ? $currentYear : $currentYear - 1;
        
        $startDate = Carbon::create($startYear, $startMonth, 1)->startOfDay();
        $endDate = $startDate->copy()->addYear()->subDay()->endOfDay();

        // Current FY
        FiscalPeriod::firstOrCreate([
            'name' => "FY {$startYear}-" . substr($startYear + 1, 2),
        ], [
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        // Previous FY
        $prevStartDate = $startDate->copy()->subYear();
        $prevEndDate = $endDate->copy()->subYear();
        FiscalPeriod::firstOrCreate([
            'name' => "FY " . ($startYear - 1) . "-" . substr($startYear, 2),
        ], [
            'start_date' => $prevStartDate,
            'end_date' => $prevEndDate,
        ]);
    }
}
