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

        Schema::create("{$prefix}credit_notes", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained("{$prefix}branches")->restrictOnDelete();
            
            $table->foreignId('contact_id')
                  ->nullable()
                  ->constrained("{$prefix}contacts")
                  ->nullOnDelete();
                  
            $table->foreignId('invoice_id')
                  ->nullable()
                  ->constrained("{$prefix}invoices")
                  ->nullOnDelete();
                  
            $table->foreignId('transaction_id')
                  ->nullable()
                  ->constrained("{$prefix}transactions")
                  ->nullOnDelete();
                  
            $table->string('credit_note_number', 30);
            
            $table->string('reference')->nullable();
            $table->nullableMorphs('reference', "{$prefix}cn_reference_idx");
            $table->string('status', 20)->default('draft');
            
            $table->date('issue_date');
            $table->string('reason')->nullable();
            
            $table->text('notes')->nullable();
            
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('grand_total')->default(0);
            
            $table->bigInteger('applied_amount')->default(0);
            $table->bigInteger('balance_remaining')->default(0);
            
            $table->json('billing_address_snapshot')->nullable();
                  
            $table->timestamps();
            
            $table->unique(['branch_id', 'credit_note_number'], "{$prefix}cns_number_unique");
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}credit_notes");
    }
};
