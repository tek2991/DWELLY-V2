<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->char('parent_id', 26)->nullable();
            $table->integer('level')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->foreign('parent_id')->references('id')->on('regions')->nullOnDelete();
        });

        Schema::create('staff_regions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('region_id', 26);
            $table->timestamp('created_at')->nullable();

            $table->foreign('region_id')->references('id')->on('regions')->cascadeOnDelete();
            $table->unique(['user_id', 'region_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_regions');
        Schema::dropIfExists('regions');
    }
};
