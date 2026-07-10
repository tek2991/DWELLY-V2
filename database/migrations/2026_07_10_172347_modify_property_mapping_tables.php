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
        Schema::table('property_inventories', function (Blueprint $table) {
            $table->char('property_room_id', 26)->nullable()->after('inventory_type_id');
            $table->foreign('property_room_id')->references('id')->on('property_rooms')->cascadeOnDelete();
            
            // Uniqueness constraint: property_id + inventory_type_id + property_room_id (if null, room is just "general property inventory")
            // Wait, in SQL server unique constraint treats multiple nulls as duplicates. But we are likely using MySQL or PostgreSQL. 
            // In standard MySQL/Postgres, UNIQUE constraint allows multiple NULLs! This means [property_id, inventory_type_id, NULL] can appear multiple times.
            // To prevent this, we'll enforce uniqueness at the application level as well.
            $table->unique(['property_id', 'inventory_type_id', 'property_room_id'], 'prop_inv_unique');
        });

        Schema::table('property_rooms', function (Blueprint $table) {
            $table->unique(['property_id', 'room_type_id']);
        });

        Schema::table('property_amenities', function (Blueprint $table) {
            $table->unique(['property_id', 'amenity_type_id']);
        });

        Schema::table('property_establishments', function (Blueprint $table) {
            $table->unique(['property_id', 'establishment_id']);
        });
    }

    public function down(): void
    {
        Schema::table('property_establishments', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'establishment_id']);
        });

        Schema::table('property_amenities', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'amenity_type_id']);
        });

        Schema::table('property_rooms', function (Blueprint $table) {
            $table->dropUnique(['property_id', 'room_type_id']);
        });

        Schema::table('property_inventories', function (Blueprint $table) {
            $table->dropUnique('prop_inv_unique');
            $table->dropForeign(['property_room_id']);
            $table->dropColumn('property_room_id');
        });
    }
};
