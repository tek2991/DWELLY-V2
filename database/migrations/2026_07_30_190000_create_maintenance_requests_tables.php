<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_requests', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('ticket_number')->unique();
            $table->char('property_id', 26);
            $table->char('tenant_id', 26)->nullable();
            $table->char('owner_id', 26)->nullable();
            $table->char('vendor_party_id', 26)->nullable();
            $table->foreignId('assigned_inspector_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reporter_type')->default('staff'); // tenant, owner, staff
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium'); // low, medium, high, emergency
            $table->string('status')->default('draft');

            // Financial & Settlement Breakdown
            $table->string('payer_type')->nullable(); // owner, tenant, dwelly, split
            $table->boolean('is_dwelly_involved')->default(false);
            $table->decimal('total_cost', 12, 2)->default(0.00);
            $table->decimal('vendor_cost', 12, 2)->default(0.00);
            $table->decimal('dwelly_amount', 12, 2)->default(0.00);
            $table->decimal('owner_amount', 12, 2)->default(0.00);
            $table->decimal('tenant_amount', 12, 2)->default(0.00);
            $table->string('direct_payment_reference')->nullable();
            $table->text('direct_payment_notes')->nullable();

            // Generated Accounting & Audit Links
            $table->string('bill_id')->nullable();
            $table->string('owner_invoice_id')->nullable();
            $table->string('tenant_invoice_id')->nullable();
            $table->uuid('triggered_audit_id')->nullable();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('parties')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('parties')->nullOnDelete();
            $table->foreign('vendor_party_id')->references('id')->on('parties')->nullOnDelete();
            $table->foreign('triggered_audit_id')->references('id')->on('audits')->nullOnDelete();
        });

        Schema::create('maintenance_request_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('maintenance_request_id', 26);
            $table->string('itemable_type')->nullable();
            $table->char('itemable_id', 26)->nullable();
            $table->text('issue_description')->nullable();
            $table->string('repair_action')->nullable();
            $table->decimal('estimated_cost', 12, 2)->default(0.00);
            $table->decimal('actual_cost', 12, 2)->default(0.00);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('maintenance_request_id')->references('id')->on('maintenance_requests')->cascadeOnDelete();
            $table->index(['itemable_type', 'itemable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_request_items');
        Schema::dropIfExists('maintenance_requests');
    }
};
