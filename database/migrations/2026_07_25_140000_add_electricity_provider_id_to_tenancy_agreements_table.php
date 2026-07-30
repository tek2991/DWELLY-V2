<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            $table->char('electricity_provider_id', 26)->nullable()->after('apdcl_consumer_id');
            $table->foreign('electricity_provider_id')->references('id')->on('utility_providers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            $table->dropForeign(['electricity_provider_id']);
            $table->dropColumn(['electricity_provider_id']);
        });
    }
};
