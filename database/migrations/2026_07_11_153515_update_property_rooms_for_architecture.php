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
        Schema::dropIfExists('property_rooms');
        
        Schema::create('property_rooms', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('property_id', 26);
            $table->char('room_definition_id', 26);
            $table->string('custom_name')->nullable();
            $table->integer('floor')->nullable();
            $table->decimal('area', 8, 2)->nullable();
            $table->text('description')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('room_definition_id')->references('id')->on('room_definitions')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_rooms', function (Blueprint $table) {
            $table->dropForeign(['room_definition_id']);
            $table->dropColumn([
                'room_definition_id', 'custom_name', 'floor', 'area', 'description', 'display_order', 'is_active'
            ]);
            $table->char('room_type_id', 26)->nullable();
            $table->integer('count')->default(1);
            $table->foreign('room_type_id')->references('id')->on('room_types')->cascadeOnDelete();
        });
    }
};
