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
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropForeign(['lead_origin_id']);
            $table->dropColumn('lead_origin_id');
        });

        Schema::dropIfExists('lead_origins');

        Schema::table('opportunity_sources', function (Blueprint $table) {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('opportunity_sources')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('opportunity_sources', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });

        Schema::create('lead_origins', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('lead_origin_id')->nullable()->after('id')->constrained('lead_origins')->nullOnDelete();
        });
    }
};
