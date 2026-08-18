<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('city_establishment_type', function (Blueprint $table) {
            $table->id();
            $table->char('city_id', 26);
            $table->char('establishment_type_id', 26);
            $table->timestamps();

            $table->foreign('city_id')->references('id')->on('cities')->cascadeOnDelete();
            $table->foreign('establishment_type_id')->references('id')->on('establishment_types')->cascadeOnDelete();
            $table->unique(['city_id', 'establishment_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('city_establishment_type');
    }
};
