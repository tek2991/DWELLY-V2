<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mous', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('number')->unique(); // E.g., MOU-2026-0001
            $table->integer('version')->default(1);
            $table->date('start_date')->nullable();
            $table->foreignUlid('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            
            $table->foreignUlid('property_id')->nullable()->constrained('properties')->cascadeOnDelete();
            $table->string('type')->default('onboarding');

            // The resolved Party after Party Resolution
            $table->foreignUlid('party_id')->nullable()->constrained('parties');
            $table->boolean('is_signatory_different')->default(false);
            $table->json('signatory_details')->nullable();
            
            $table->string('status'); // Draft, Generated, Signed, Verified, Cancelled
            
            // Core legal & commercial data required for the MOU
            $table->json('legal_terms')->nullable(); // Rent, deposit, lock-in, notice period
            $table->json('bank_details')->nullable(); // Account number, IFSC, Bank Name
            
            // Document Tracking (Using Spatie Media Library is better, but we can store paths if needed)
            // But we already have MediaLibrary in DomainModel, so we might just use that.
            
            $table->timestamp('verified_at')->nullable();
            $table->foreignUlid('verified_by')->nullable()->constrained('users');
            
            $table->foreignUlid('prepared_by')->nullable()->constrained('users');
            $table->foreignUlid('generated_by')->nullable()->constrained('users');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mous');
    }
};
