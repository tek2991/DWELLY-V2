<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            $table->json('secondary_tenants')->nullable()->after('electricity_provider_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            $table->dropColumn('secondary_tenants');
        });
    }
};
