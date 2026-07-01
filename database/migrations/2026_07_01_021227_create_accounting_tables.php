<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rent_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenancy_agreement_id', 26);
            $table->char('tenant_id', 26); // party_id
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending'); // pending, paid, overdue, failed
            $table->date('due_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->string('reference_number')->nullable();
            $table->timestamps();

            $table->foreign('tenancy_agreement_id')->references('id')->on('tenancy_agreements')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('parties')->restrictOnDelete();
        });

        Schema::create('vendor_bills', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('vendor_id', 26); // party_id
            $table->char('property_id', 26)->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('draft'); // draft, approved, paid, cancelled
            $table->text('description')->nullable();
            $table->date('bill_date')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();

            $table->foreign('vendor_id')->references('id')->on('parties')->restrictOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->restrictOnDelete();
        });

        Schema::create('owner_payouts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('owner_id', 26); // party_id
            $table->char('property_id', 26);
            $table->decimal('rent_collected', 12, 2)->default(0);
            $table->decimal('management_fee', 12, 2)->default(0);
            $table->decimal('reserve_deduction', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0); // final payout amount
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('owner_id')->references('id')->on('parties')->restrictOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->restrictOnDelete();
        });

        Schema::create('journal_entries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulidMorphs('trackable'); // Morph to RentPayment, VendorBill, OwnerPayout
            $table->decimal('debit', 12, 2)->default(0);
            $table->decimal('credit', 12, 2)->default(0);
            $table->string('account_code'); // Mapping to tek2991/accounting
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('owner_payouts');
        Schema::dropIfExists('vendor_bills');
        Schema::dropIfExists('rent_payments');
    }
};
