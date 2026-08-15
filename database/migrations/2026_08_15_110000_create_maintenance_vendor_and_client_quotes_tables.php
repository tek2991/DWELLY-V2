<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_vendor_quotes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('maintenance_request_id', 26);
            $table->char('vendor_party_id', 26);
            $table->char('vendor_trade_id', 26)->nullable();
            $table->string('trade_title')->nullable();
            $table->text('scope_of_work')->nullable();
            $table->decimal('quoted_cost', 12, 2)->default(0.00);
            $table->decimal('final_cost', 12, 2)->nullable();
            $table->string('status')->default('quote_received');
            $table->string('bill_id')->nullable();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('maintenance_request_id')->references('id')->on('maintenance_requests')->cascadeOnDelete();
            $table->foreign('vendor_party_id')->references('id')->on('parties')->cascadeOnDelete();
            $table->foreign('vendor_trade_id')->references('id')->on('vendor_trades')->nullOnDelete();
        });

        Schema::create('maintenance_client_quotes', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('maintenance_request_id', 26);
            $table->string('quote_number')->unique();
            $table->decimal('total_amount', 12, 2)->default(0.00);
            $table->decimal('owner_amount', 12, 2)->default(0.00);
            $table->decimal('tenant_amount', 12, 2)->default(0.00);
            $table->decimal('dwelly_amount', 12, 2)->default(0.00);
            $table->string('status')->default('draft'); // draft, pending_approval, approved, rejected, superseded
            $table->text('rejection_reason')->nullable();
            $table->string('rejection_action')->nullable(); // revert_to_direct, cancel, requote
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();
            $table->timestamps();

            $table->foreign('maintenance_request_id')->references('id')->on('maintenance_requests')->cascadeOnDelete();
        });

        Schema::create('maintenance_client_quote_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('maintenance_client_quote_id', 26);
            $table->char('vendor_quote_id', 26)->nullable();
            $table->string('description');
            $table->decimal('quantity', 8, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0.00);
            $table->decimal('total_price', 12, 2)->default(0.00);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('maintenance_client_quote_id')->references('id')->on('maintenance_client_quotes')->cascadeOnDelete();
            $table->foreign('vendor_quote_id')->references('id')->on('maintenance_vendor_quotes')->nullOnDelete();
        });

        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->char('current_client_quote_id', 26)->nullable()->after('is_direct_vendor');
            $table->decimal('total_vendor_cost', 12, 2)->default(0.00)->after('vendor_cost');
            $table->decimal('total_client_cost', 12, 2)->default(0.00)->after('total_vendor_cost');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn([
                'current_client_quote_id',
                'total_vendor_cost',
                'total_client_cost',
            ]);
        });

        Schema::dropIfExists('maintenance_client_quote_items');
        Schema::dropIfExists('maintenance_client_quotes');
        Schema::dropIfExists('maintenance_vendor_quotes');
    }
};
