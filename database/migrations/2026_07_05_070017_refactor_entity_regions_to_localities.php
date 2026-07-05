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
        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
            $table->char('locality_id', 26)->nullable();
            $table->foreign('locality_id')->references('id')->on('localities')->nullOnDelete();
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->dropForeign(['region_id']);
            $table->dropColumn('region_id');
            $table->char('locality_id', 26)->nullable();
            $table->foreign('locality_id')->references('id')->on('localities')->nullOnDelete();
        });

        Schema::dropIfExists('staff_regions');
        Schema::dropIfExists('regions');
    }

    public function down(): void
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

        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['locality_id']);
            $table->dropColumn('locality_id');
            $table->char('region_id', 26)->nullable();
            $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->dropForeign(['locality_id']);
            $table->dropColumn('locality_id');
            $table->char('region_id', 26)->nullable();
            $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
        });
    }
};
