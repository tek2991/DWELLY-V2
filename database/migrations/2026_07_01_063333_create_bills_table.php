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

        Schema::create("{$prefix}bills", function (Blueprint $table) use ($prefix) {
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
                  
            $table->string('bill_number', 30);
            
            $table->string('reference')->nullable();
            $table->nullableMorphs('reference', "{$prefix}bill_reference_idx");
            
            $table->string('vendor_reference', 100)->nullable();
            $table->string('seller_invoice_path')->nullable();
            $table->string('status', 20)->default('draft');
            
            $table->date('issue_date');
            $table->date('due_date')->nullable();
            
            $table->string('currency_code', 3)->default(config('accounting.default_currency', 'INR'));
            $table->decimal('exchange_rate', 15, 6)->default(1);
            
            $table->foreignId('place_of_supply_state_id')
                  ->nullable()
                  ->constrained("{$prefix}states")
                  ->nullOnDelete();
                  
            $table->text('notes')->nullable();
            
            $table->bigInteger('subtotal')->default(0);
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_rate', 8, 4)->nullable();
            $table->bigInteger('discount_amount')->default(0);
            
            $table->foreignId('discount_account_id')
                  ->nullable()
                  ->constrained("{$prefix}accounts")
                  ->nullOnDelete();
                  
            $table->string('tax_computation_mode', 20)->default('exclusive');
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('grand_total')->default(0);
            $table->bigInteger('amount_paid')->default(0);
            $table->bigInteger('balance_due')->default(0);
            
            $table->foreignId('default_expense_account_id')
                  ->nullable()
                  ->constrained("{$prefix}accounts")
                  ->nullOnDelete();
                  
            $table->timestamps();
            
            $table->unique(['branch_id', 'bill_number'], "{$prefix}bills_number_unique");
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}bills");
    }
};
