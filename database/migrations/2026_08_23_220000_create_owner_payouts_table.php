<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('owner_payouts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignUlid('owner_id')->constrained('parties')->cascadeOnDelete();
            $table->foreignUlid('property_id')->constrained('properties')->cascadeOnDelete();
            $table->unsignedBigInteger('transaction_id')->nullable();
            
            $table->date('period_start');
            $table->date('period_end');
            
            $table->decimal('rent_collected', 12, 2)->default(0);
            $table->decimal('management_fee', 12, 2)->default(0);
            $table->decimal('advance_offset', 12, 2)->default(0);
            $table->decimal('reserve_deduction', 12, 2)->default(0);
            $table->decimal('amount', 12, 2)->default(0); // net payout
            
            $table->string('status', 30)->default('pending');
            $table->text('notes')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'property_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('owner_payouts');
    }
};
