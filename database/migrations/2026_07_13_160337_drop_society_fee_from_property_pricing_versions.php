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
        Schema::table('property_pricing_versions', function (Blueprint $table) {
            $table->dropColumn('society_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('property_pricing_versions', function (Blueprint $table) {
            $table->decimal('society_fee', 12, 2)->nullable();
        });
    }
};
