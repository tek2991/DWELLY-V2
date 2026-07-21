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
        Schema::table('party_bank_accounts', function (Blueprint $table) {
            $table->renameColumn('account_name', 'beneficiary_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('party_bank_accounts', function (Blueprint $table) {
            $table->renameColumn('beneficiary_name', 'account_name');
        });
    }
};
