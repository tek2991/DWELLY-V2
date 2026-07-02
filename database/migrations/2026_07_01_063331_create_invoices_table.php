<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function prefix(): string
    {
        return config('accounting.table_prefix', 'acc_');
    }

    public function up(): void
    {
        $prefix = $this->prefix();

        Schema::create("{$prefix}invoices", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained("branches")->restrictOnDelete();
            
            $table->foreignId('contact_id')
                  ->nullable()
                  ->constrained("{$prefix}contacts")
                  ->nullOnDelete();
                  
            $table->foreignId('transaction_id')
                  ->nullable()
                  ->constrained("{$prefix}transactions")
                  ->nullOnDelete();
                  
            $table->string('invoice_number', 30);
            
            $table->string('reference')->nullable();
            $table->nullableMorphs('reference', "{$prefix}inv_reference_idx");
            
            $table->string('status', 20)->default('draft');
            
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            
            $table->string('currency_code', 3)->default(config('accounting.default_currency', 'INR'));
            $table->decimal('exchange_rate', 15, 6)->default(1);
            
            $table->json('billing_address_snapshot')->nullable();
            
            $table->foreignId('place_of_supply_state_id')
                  ->nullable()
                  ->constrained("{$prefix}states")
                  ->nullOnDelete();
            
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            
            $table->bigInteger('subtotal')->default(0);
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_rate', 8, 4)->nullable();
            $table->bigInteger('discount_amount')->default(0);
            
            $table->foreignId('discount_account_id')
                  ->nullable()
                  ->constrained("{$prefix}accounts")
                  ->nullOnDelete();
                  
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('grand_total')->default(0);
            $table->bigInteger('amount_paid')->default(0);
            $table->bigInteger('balance_due')->default(0);
            
            $table->foreignId('default_income_account_id')
                  ->nullable()
                  ->constrained("{$prefix}accounts")
                  ->nullOnDelete();
                  
            $table->timestamps();
            
            $table->unique(['branch_id', 'invoice_number'], "{$prefix}invoices_number_unique");
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}invoices");
    }
};
