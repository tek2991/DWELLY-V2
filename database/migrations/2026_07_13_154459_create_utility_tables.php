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
        Schema::dropIfExists('property_utility_config');

        Schema::create('utility_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('property_utilities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('property_id', 26);
            $table->char('utility_type_id', 26);
            $table->string('paid_by'); // owner, tenant, dwelly
            $table->decimal('amount', 12, 2)->nullable();
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->string('details')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('utility_type_id')->references('id')->on('utility_types')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_utilities');
        Schema::dropIfExists('utility_types');
    }
};
