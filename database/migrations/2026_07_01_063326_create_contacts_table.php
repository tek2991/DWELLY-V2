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

        Schema::create("{$prefix}contacts", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->char('party_id', 26)->nullable();
            
            $table->string('type')->default('customer'); // customer, vendor, both
            
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            
            $table->string('tax_id')->nullable(); // GSTIN, VAT number, etc.
            
            $table->foreignId('state_id')
                  ->nullable()
                  ->constrained("{$prefix}states")
                  ->nullOnDelete();
            
            $table->boolean('is_tax_registered')->default(false);
            $table->string('gstin', 15)->nullable();
            $table->string('gst_registration_type')->nullable();
            
            $table->text('billing_address')->nullable();
            $table->text('shipping_address')->nullable();
            
            // Outstanding balances (denormalized for performance)
            $table->bigInteger('receivable_balance')->default(0); // what they owe us
            $table->bigInteger('payable_balance')->default(0); // what we owe them
            
            // Bank Details
            $table->string('bank_beneficiary_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('bank_address')->nullable();
            $table->string('bank_account_no')->nullable();
            $table->string('bank_ifsc_code')->nullable();

            $table->foreign('party_id')->references('id')->on('parties')->nullOnDelete();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}contacts");
    }
};
