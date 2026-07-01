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

        Schema::create("{$prefix}debit_notes", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            
            $table->foreignId('contact_id')
                  ->nullable()
                  ->constrained("{$prefix}contacts")
                  ->nullOnDelete();
                  
            $table->foreignId('bill_id')
                  ->nullable()
                  ->constrained("{$prefix}bills")
                  ->nullOnDelete();
                  
            $table->foreignId('transaction_id')
                  ->nullable()
                  ->constrained("{$prefix}transactions")
                  ->nullOnDelete();
                  
            $table->string('debit_note_number', 30);
            $table->string('status', 20)->default('draft');
            
            $table->date('issue_date');
            $table->string('reason')->nullable();
            
            $table->text('notes')->nullable();
            
            $table->bigInteger('subtotal')->default(0);
            $table->bigInteger('tax_total')->default(0);
            $table->bigInteger('grand_total')->default(0);
            
            $table->bigInteger('applied_amount')->default(0);
            $table->bigInteger('balance_remaining')->default(0);
            
            $table->timestamps();
            
            $table->unique(['company_id', 'debit_note_number']);
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}debit_notes");
    }
};
