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
        Schema::table('furnishing_types', function (Blueprint $table) {
            $table->string('inventory_validation_rule')->default('skip')->after('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('furnishing_types', function (Blueprint $table) {
            $table->dropColumn('inventory_validation_rule');
        });
    }
};
