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
        Schema::create('audit_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->foreignUuid('audit_category_id')->constrained('audit_categories')->cascadeOnDelete();
            
            $table->string('name');
            
            // Polymorphic relation to the source of truth (Room, Inventory, Utility, etc.)
            $table->nullableUuidMorphs('source');
            
            // Immutable snapshot of essential fields at creation time
            $table->json('snapshot_data')->nullable();
            
            // Item state
            $table->string('status')->default('pending');
            $table->string('condition')->nullable();
            $table->text('remarks')->nullable();
            
            $table->integer('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_items');
    }
};
