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
            $table->foreign('property_room_id')->references('id')->on('property_rooms')->nullOnDelete();
            
            $table->dropUnique('prop_inv_unique_new');
            $table->unique(['property_id', 'inventory_type_id', 'property_room_id'], 'prop_inv_unique_room');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_inventories', function (Blueprint $table) {
            $table->dropUnique('prop_inv_unique_room');
            $table->dropForeign(['property_room_id']);
            $table->dropColumn('property_room_id');
            
            $table->unique(['property_id', 'inventory_type_id'], 'prop_inv_unique_new');
        });
    }
};
