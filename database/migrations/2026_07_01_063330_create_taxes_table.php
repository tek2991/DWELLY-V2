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

        Schema::create("{$prefix}taxes", function (Blueprint $table) use ($prefix) {
            $table->id();
            
            $table->string('name'); // e.g. GST 18%
            $table->string('description')->nullable();
            
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create("{$prefix}tax_components", function (Blueprint $table) use ($prefix) {
            $table->id();
            
            $table->foreignId('tax_id')
                  ->constrained("{$prefix}taxes")
                  ->cascadeOnDelete();
                  
            $table->string('name'); // e.g. CGST
            $table->string('type')->default('generic'); // intrastate, interstate, union_territory, generic
            
            // Storing percentage as decimal. E.g. 9.0000
            $table->decimal('rate', 8, 4);
            
            // Account to post the tax to on sales (usually a Liability account)
            $table->foreignId('sales_account_id')
                  ->constrained("{$prefix}accounts")
                  ->restrictOnDelete();
                  
            // Account to post the tax to on purchases (usually an Asset/ITC account)
            $table->foreignId('purchase_account_id')
                  ->constrained("{$prefix}accounts")
                  ->restrictOnDelete();
                  
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}tax_components");
        Schema::dropIfExists("{$prefix}taxes");
    }
};
