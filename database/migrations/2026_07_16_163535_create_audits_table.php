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
        Schema::create('audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Core identity
            $table->string('audit_number')->unique();
            $table->foreignUuid('property_id')->constrained('properties')->cascadeOnDelete();
            
            // Domain ENUMs
            $table->string('audit_type');
            $table->string('status')->default('draft');
            
            // Reference
            $table->foreignUuid('reference_audit_id')->nullable()->constrained('audits')->nullOnDelete();
            
            // Assignment & Tracking
            $table->foreignUuid('inspector_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->dateTime('scheduled_at')->nullable();
            
            $table->dateTime('completed_at')->nullable();
            $table->foreignUuid('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->dateTime('approved_at')->nullable();
            $table->foreignUuid('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            
            $table->text('notes')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audits');
    }
};
