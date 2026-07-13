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
        Schema::create('property_documents', function (Blueprint $table) {
            $table->ulid('id')->primary();
            
            $table->char('property_id', 26);
            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            
            $table->char('property_room_id', 26)->nullable();
            $table->foreign('property_room_id')->references('id')->on('property_rooms')->nullOnDelete();
            
            $table->string('document_category');
            $table->string('title')->nullable();
            $table->string('file_path');
            
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('property_documents');
    }
};
