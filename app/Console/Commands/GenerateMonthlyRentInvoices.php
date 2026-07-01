<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Agreement\Models\TenancyAgreement;
use App\Domain\Finance\Services\AccountingBridgeService;
use App\Domain\Finance\Models\RentPayment;

class GenerateMonthlyRentInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'finance:generate-rent-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generates monthly rent invoices for all active tenancies';

    /**
     * Execute the console command.
     */
    public function handle(AccountingBridgeService $accounting)
    {
        $this->info('Starting monthly rent invoice generation...');
        
        $activeAgreements = TenancyAgreement::where('status', 'active')->get();
        
        $count = 0;
        foreach ($activeAgreements as $agreement) {
            // Check if a payment for this month already exists to prevent duplicates
            $existingPayment = \App\Domain\Finance\Models\RentPayment::where('tenancy_agreement_id', $agreement->id)
                ->whereYear('due_date', now()->year)
                ->whereMonth('due_date', now()->month)
                ->exists();
                
            if ($existingPayment) {
                continue;
            }
            
            // Find the primary tenant
            $primaryRole = $agreement->roles()->where('is_primary', true)->first();
            if (!$primaryRole) {
                $this->warn("No primary tenant for agreement {$agreement->code}, skipping.");
                continue;
            }

            // Create a properly persisted pending rent payment
            $payment = \App\Domain\Finance\Models\RentPayment::create([
                'tenancy_agreement_id' => $agreement->id,
                'tenant_id' => $primaryRole->party_id,
                'amount' => $agreement->rent_amount,
                'status' => 'pending',
                'due_date' => now()->startOfMonth(),
            ]);
            
            $accounting->recordRentPayment($payment);
            
            $count++;
        }
        
        $this->info("Successfully generated {$count} rent invoices.");
    }
}
