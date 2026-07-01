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

        Schema::create("{$prefix}credit_note_items", function (Blueprint $table) use ($prefix) {
            $table->id();
            
            $table->foreignId('credit_note_id')
                  ->constrained("{$prefix}credit_notes")
                  ->cascadeOnDelete();
                  
            $table->foreignId('item_id')
                  ->nullable()
                  ->constrained("{$prefix}items")
                  ->nullOnDelete();
                  
            $table->unsignedSmallInteger('sort_order')->default(0);
            
            $table->string('description', 500);
            $table->string('hsn_sac_code', 20)->nullable();
            
            $table->decimal('quantity', 12, 4)->default(1);
            $table->bigInteger('unit_price')->default(0);
            $table->bigInteger('gross_amount')->default(0);
            
            $table->string('discount_type', 20)->nullable();
            $table->decimal('discount_rate', 8, 4)->nullable();
            $table->bigInteger('discount_amount')->default(0);
            $table->bigInteger('line_discount_amount')->default(0);
            $table->bigInteger('allocated_document_discount')->default(0);
            $table->bigInteger('net_amount')->default(0);
            
            $table->bigInteger('line_total')->default(0);
            
            $table->foreignId('tax_id')
                  ->nullable()
                  ->constrained("{$prefix}taxes")
                  ->nullOnDelete();
                  
            $table->json('tax_snapshot')->nullable();
            $table->bigInteger('tax_amount')->default(0);
                  
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}credit_note_items");
    }
};
