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
        Schema::create('audit_item_revisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('audit_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('updated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('snapshot_data')->nullable(); // captures condition, remarks, evidence, annotations
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_item_revisions');
    }
};
