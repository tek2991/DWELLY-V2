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
        Schema::create('onboarding_projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('property_id', 26)->unique();
            $table->string('status')->default('Draft'); // Draft, In Review, Ready, Activated
            $table->foreignId('assigned_executive_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('onboarding_projects');
    }
};
