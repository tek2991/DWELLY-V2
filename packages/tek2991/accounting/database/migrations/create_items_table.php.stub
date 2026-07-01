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

        Schema::create("{$prefix}items", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            
            $table->string('type')->default('goods'); // 'goods' or 'services'
            $table->string('name');
            $table->string('sku')->nullable();
            $table->text('description')->nullable();
            
            $table->string('hsn_sac')->nullable(); // HSN/SAC code for taxes
            
            // Default Sales/Income account
            $table->foreignId('income_account_id')->nullable()
                  ->constrained("{$prefix}accounts")->nullOnDelete();
                  
            // Default Expense/COGS account
            $table->foreignId('expense_account_id')->nullable()
                  ->constrained("{$prefix}accounts")->nullOnDelete();
                  
            $table->unsignedBigInteger('sale_price')->default(0); // in cents/minor units
            $table->unsignedBigInteger('purchase_price')->default(0); // in cents/minor units
            
            $table->boolean('sellable')->default(true);
            $table->boolean('purchasable')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
            
            // Add company-specific unique index on sku
            $table->unique(['company_id', 'sku'], "{$prefix}items_sku_company_unique");
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}items");
    }
};
