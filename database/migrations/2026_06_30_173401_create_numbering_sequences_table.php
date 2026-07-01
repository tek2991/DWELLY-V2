<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('numbering_sequences', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('entity_type')->unique();
            $table->string('prefix');
            $table->boolean('include_year')->default(true);
            $table->integer('last_sequence')->default(0);
            $table->integer('year')->nullable();
            $table->integer('pad_length')->default(4);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('numbering_sequences');
    }
};
