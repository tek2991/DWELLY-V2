<?php

namespace App\Console\Commands;

use App\Domain\Finance\Services\RentBillingService;
use Illuminate\Console\Command;

class GenerateMonthlyRentInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:generate-rent-invoices {--month=} {--year=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates monthly pass-through rent invoices for all active tenancies';

    /**
     * Execute the console command.
     */
    public function handle(RentBillingService $rentBilling)
    {
        $month = (int) ($this->option('month') ?: now()->month);
        $year = (int) ($this->option('year') ?: now()->year);

        $monthName = date('F Y', mktime(0, 0, 0, $month, 1, $year));
        $this->info("Starting monthly rent demand generation for {$monthName}...");

        $count = $rentBilling->bulkGenerateRentInvoices($month, $year);

        $this->info("Successfully generated {$count} rent invoices for {$monthName}.");
    }
}

