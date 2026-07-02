<?php

namespace Tek2991\Accounting\Commands;

use Illuminate\Console\Command;
use Tek2991\Accounting\Models\FiscalPeriod;
use Tek2991\Accounting\Enums\FiscalPeriodStatus;
use Carbon\Carbon;

class GenerateFiscalPeriodsCommand extends Command
{
    protected $signature = 'accounting:generate-periods 
                            {year : The year to generate periods for (e.g., 2024)}
                            {--start_month=1 : The month the fiscal year starts (1-12)}
                            {--frequency=monthly : The frequency of periods (monthly, quarterly, yearly)}';

    protected $description = 'Auto-generate fiscal periods for a company';

    public function handle()
    {
        $year = (int) $this->argument('year');
        $startMonth = (int) $this->option('start_month');
        $frequency = $this->option('frequency');

        if ($startMonth < 1 || $startMonth > 12) {
            $this->error("Start month must be between 1 and 12.");
            return 1;
        }

        $startDate = Carbon::createFromDate($year, $startMonth, 1)->startOfDay();
        $endDate = $startDate->copy()->addYear()->subDay()->endOfDay();

        $this->info("Generating {$frequency} periods from {$startDate->toDateString()} to {$endDate->toDateString()}");

        $currentStart = $startDate->copy();
        
        while ($currentStart < $endDate) {
            $currentEnd = $currentStart->copy();
            
            if ($frequency === 'monthly') {
                $currentEnd->addMonth()->subDay()->endOfDay();
                $name = $currentStart->format('F Y');
            } elseif ($frequency === 'quarterly') {
                $currentEnd->addMonths(3)->subDay()->endOfDay();
                $name = "Q" . ceil($currentStart->month / 3) . " " . $currentStart->year;
            } elseif ($frequency === 'yearly') {
                $currentEnd->addYear()->subDay()->endOfDay();
                $name = "FY " . $currentStart->year;
            } else {
                $this->error("Invalid frequency: {$frequency}");
                return 1;
            }

            // Cap the last period to the end of the fiscal year
            if ($currentEnd > $endDate) {
                $currentEnd = $endDate->copy();
            }

            FiscalPeriod::firstOrCreate([
                'start_date' => $currentStart->toDateString(),
                'end_date' => $currentEnd->toDateString(),
            ], [
                'name' => $name,
                'status' => FiscalPeriodStatus::Open,
            ]);

            $this->line("Created period: {$name} ({$currentStart->toDateString()} to {$currentEnd->toDateString()})");

            $currentStart = $currentEnd->copy()->addDay()->startOfDay();
        }

        $this->info("Fiscal periods generated successfully!");
        return 0;
    }
}
