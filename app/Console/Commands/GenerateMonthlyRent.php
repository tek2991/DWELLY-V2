<?php

namespace App\Console\Commands;

use App\Domain\Finance\Services\RentBillingService;
use Illuminate\Console\Command;

class GenerateMonthlyRent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:generate-monthly-rent {--month=} {--year=}';

    protected $aliases = ['finance:generate-rent-invoices'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates monthly pass-through rent demands & ledger charges for all active tenancies';

    /**
     * Execute the console command.
     */
    public function handle(RentBillingService $rentBilling)
    {
        $month = (int) ($this->option('month') ?: now()->month);
        $year = (int) ($this->option('year') ?: now()->year);

        $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $this->info("Starting monthly rent demand generation for {$monthName}...");

        $count = $rentBilling->bulkGenerateRentDemands($month, $year);

        $this->info("Successfully generated {$count} rent demands for {$monthName}.");
    }
}
