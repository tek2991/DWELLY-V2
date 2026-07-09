<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('establishments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->char('establishment_type_id', 26);
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('google_place_id')->nullable();
            $table->timestamps();

            $table->foreign('establishment_type_id')->references('id')->on('establishment_types')->cascadeOnDelete();
        });

        Schema::create('properties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('code')->unique(); // e.g. GAU-0042
            $table->char('region_id', 26);
            $table->string('status'); // PropertyStatus state machine
            $table->string('building_name')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('locality')->nullable();
            $table->string('area')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('pincode')->nullable();
            $table->string('landmark')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->char('bhk_type_id', 26)->nullable();
            $table->char('property_type_id', 26)->nullable();
            $table->integer('floor')->nullable();
            $table->integer('total_floors')->nullable();
            $table->integer('floor_space_sqft')->nullable();
            $table->char('flooring_type_id', 26)->nullable();
            $table->char('furnishing_type_id', 26)->nullable();
            $table->string('pricing_model')->nullable();
            $table->boolean('is_promoted')->default(false);
            $table->date('available_from')->nullable();
            $table->foreignId('assigned_executive_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->string('archived_reason')->nullable();
            $table->timestamps();

            $table->foreign('region_id')->references('id')->on('regions')->cascadeOnDelete();
            $table->foreign('bhk_type_id')->references('id')->on('bhk_types')->nullOnDelete();
            $table->foreign('property_type_id')->references('id')->on('property_types')->nullOnDelete();
            $table->foreign('flooring_type_id')->references('id')->on('flooring_types')->nullOnDelete();
            $table->foreign('furnishing_type_id')->references('id')->on('furnishing_types')->nullOnDelete();
        });

        Schema::create('property_pricing_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('property_id', 26);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->decimal('rent', 12, 2)->nullable();
            $table->decimal('security_deposit', 12, 2)->nullable();
            $table->decimal('society_fee', 12, 2)->nullable();
            $table->string('pricing_model')->nullable(); // snapshot
            $table->decimal('fee_percentage', 5, 2)->nullable();
            $table->decimal('booking_amount', 12, 2)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });

        Schema::create('property_utility_config', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('property_id', 26)->unique();
            $table->string('electricity_billing')->default('direct_to_tenant');
            $table->string('water_billing')->default('included_in_rent');
            $table->string('society_fee_model')->default('owner_pays');
            $table->boolean('has_owner_variable_charges')->default(false);
            $table->text('variable_charge_description')->nullable();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });

        Schema::create('property_amenities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('property_id', 26);
            $table->char('amenity_type_id', 26);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('amenity_type_id')->references('id')->on('amenity_types')->cascadeOnDelete();
        });

        Schema::create('property_rooms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('property_id', 26);
            $table->char('room_type_id', 26);
            $table->integer('count')->default(1);
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('room_type_id')->references('id')->on('room_types')->cascadeOnDelete();
        });

        Schema::create('property_inventories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('property_id', 26);
            $table->char('inventory_type_id', 26);
            $table->integer('count')->default(1);
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('inventory_type_id')->references('id')->on('inventory_types')->cascadeOnDelete();
        });

        Schema::create('property_assignments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('property_id', 26);
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->string('assignment_role');
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->string('reason')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
        });

        Schema::create('property_establishments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('property_id', 26);
            $table->char('establishment_id', 26);
            $table->decimal('distance_km', 5, 2)->nullable();
            $table->integer('travel_time_minutes')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('establishment_id')->references('id')->on('establishments')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_establishments');
        Schema::dropIfExists('property_assignments');
        Schema::dropIfExists('property_inventories');
        Schema::dropIfExists('property_rooms');
        Schema::dropIfExists('property_amenities');
        Schema::dropIfExists('property_utility_config');
        Schema::dropIfExists('property_pricing_versions');
        Schema::dropIfExists('properties');
        Schema::dropIfExists('establishments');
    }
};
