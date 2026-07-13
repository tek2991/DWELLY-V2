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
        Schema::table('property_photos', function (Blueprint $table) {
            $table->char('property_room_id', 26)->nullable()->after('property_id');
            $table->foreign('property_room_id')->references('id')->on('property_rooms')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_photos', function (Blueprint $table) {
            $table->dropForeign(['property_room_id']);
            $table->dropColumn('property_room_id');
        });
    }
};
