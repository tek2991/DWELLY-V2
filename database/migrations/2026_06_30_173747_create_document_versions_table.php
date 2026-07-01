<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulidMorphs('documentable');
            $table->char('document_type_id', 26);
            $table->integer('version')->default(1);
            $table->string('status')->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('document_type_id')->references('id')->on('document_types')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
