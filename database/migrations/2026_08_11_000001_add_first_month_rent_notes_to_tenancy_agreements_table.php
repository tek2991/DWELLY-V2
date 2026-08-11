<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            $table->text('first_month_rent_notes')->nullable()->after('first_month_rent');
        });
    }

    public function down(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            $table->dropColumn('first_month_rent_notes');
        });
    }
};
