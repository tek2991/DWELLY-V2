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
        Schema::create('property_financial_terms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('property_id', 26);
            $table->char('mou_id', 26)->nullable();
            $table->string('pricing_model');
            $table->decimal('fee_percentage', 5, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            // Note: If mous table drops or uses ulid, might need constraint. Assuming mous has ulid id.
            $table->foreign('mou_id')->references('id')->on('mous')->nullOnDelete();
        });

        // Remove from property_pricing_versions
        Schema::table('property_pricing_versions', function (Blueprint $table) {
            $table->dropColumn(['pricing_model', 'fee_percentage']);
        });

        // Remove from properties
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('pricing_model');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('pricing_model')->nullable();
        });

        Schema::table('property_pricing_versions', function (Blueprint $table) {
            $table->string('pricing_model')->nullable();
            $table->decimal('fee_percentage', 5, 2)->nullable();
        });

        Schema::dropIfExists('property_financial_terms');
    }
};
