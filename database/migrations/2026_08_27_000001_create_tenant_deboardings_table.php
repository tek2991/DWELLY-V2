<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_deboardings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique();
            $table->char('tenancy_agreement_id', 26)->unique();
            $table->char('property_id', 26);
            $table->char('tenant_id', 26)->nullable();
            $table->string('status')->default('notice_served'); // DeboardingStatus enum values
            
            // Notice & Vacating Details
            $table->date('notice_date');
            $table->date('target_vacating_date');
            $table->date('actual_vacating_date')->nullable();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();

            // Phase 1: Exit Audit Reference
            $table->char('move_out_audit_id', 26)->nullable();
            $table->boolean('damages_identified')->default(false);
            $table->text('damage_notes')->nullable();

            // Phase 2: Maintenance Linkage & Estimates
            $table->decimal('total_repair_cost', 12, 2)->default(0.00);
            $table->decimal('tenant_repair_share', 12, 2)->default(0.00);
            $table->decimal('owner_repair_share', 12, 2)->default(0.00);
            $table->decimal('dwelly_repair_share', 12, 2)->default(0.00);

            // Phase 3: Keys & Security Deposit Settlement
            $table->boolean('keys_returned')->default(false);
            $table->timestamp('keys_returned_at')->nullable();
            $table->foreignId('keys_received_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('key_handover_remarks')->nullable();
            $table->json('key_details')->nullable();

            // Financial Deductions Breakdown
            $table->decimal('security_deposit_held', 12, 2)->default(0.00);
            $table->decimal('unpaid_rent_deduction', 12, 2)->default(0.00);
            $table->decimal('maintenance_deduction', 12, 2)->default(0.00);
            $table->decimal('utility_deduction', 12, 2)->default(0.00);
            $table->decimal('other_deductions', 12, 2)->default(0.00);
            $table->text('other_deductions_notes')->nullable();
            $table->decimal('total_deductions', 12, 2)->default(0.00);
            $table->decimal('net_deposit_refund', 12, 2)->default(0.00);
            $table->decimal('excess_due_from_tenant', 12, 2)->default(0.00);
            $table->string('settlement_status')->default('pending'); // pending, refunded, balance_due, settled

            // Refund Payment Details
            $table->string('refund_payment_mode')->nullable(); // Bank Transfer / NEFT, UPI, Cheque, Cash
            $table->string('refund_transaction_reference')->nullable();
            $table->json('refund_bank_details')->nullable();
            $table->timestamp('refunded_at')->nullable();

            // Handover & Property Status Transition
            $table->string('new_property_status')->default('vacant');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenancy_agreement_id')->references('id')->on('tenancy_agreements')->cascadeOnDelete();
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('parties')->nullOnDelete();
            $table->foreign('move_out_audit_id')->references('id')->on('audits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_deboardings');
    }
};
