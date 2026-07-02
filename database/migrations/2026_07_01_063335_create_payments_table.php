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

        Schema::create("{$prefix}payments", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained("branches")->restrictOnDelete();
            
            $table->string('paymentable_type', 100);
            $table->unsignedBigInteger('paymentable_id');
            
            $table->foreignId('transaction_id')
                  ->nullable()
                  ->constrained("{$prefix}transactions")
                  ->nullOnDelete();
                  
            $table->foreignId('payment_account_id')
                  ->constrained("{$prefix}accounts")
                  ->restrictOnDelete();
                  
            $table->bigInteger('amount');
            $table->date('payment_date');
            
            $table->string('reference', 100)->nullable();
            $table->text('notes')->nullable();
            
            $table->timestamps();
            
            $table->index(['paymentable_type', 'paymentable_id']);
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}payments");
    }
};
