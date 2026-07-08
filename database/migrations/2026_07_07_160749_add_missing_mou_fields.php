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
        Schema::table('party_individuals', function (Blueprint $table) {
            $table->string('parent_name')->nullable()->after('last_name');
        });

        Schema::table('party_bank_accounts', function (Blueprint $table) {
            $table->string('branch_address')->nullable()->after('ifsc_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('party_bank_accounts', function (Blueprint $table) {
            $table->dropColumn('branch_address');
        });

        Schema::table('party_individuals', function (Blueprint $table) {
            $table->dropColumn('parent_name');
        });
    }
};
