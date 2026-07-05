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
        Schema::create('districts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('state_id')->constrained('acc_states')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('district_id', 26);
            $table->string('name');
            $table->string('slug')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('district_id')->references('id')->on('districts')->cascadeOnDelete();
        });

        Schema::create('localities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('city_id', 26);
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('pincode')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('city_id')->references('id')->on('cities')->cascadeOnDelete();
        });

        Schema::create('staff_geographic_access', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('area_type'); // State, District, City, Locality
            $table->string('area_id'); // ID of the specific area (string because it could be ULID or BigInt for State)
            $table->timestamps();

            $table->index(['area_type', 'area_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_geographic_access');
        Schema::dropIfExists('localities');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('districts');
    }
};
