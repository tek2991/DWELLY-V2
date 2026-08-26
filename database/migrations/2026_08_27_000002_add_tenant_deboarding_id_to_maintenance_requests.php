<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->char('tenant_deboarding_id', 26)->nullable()->after('tenant_id');
            $table->foreign('tenant_deboarding_id')->references('id')->on('tenant_deboardings')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropForeign(['tenant_deboarding_id']);
            $table->dropColumn('tenant_deboarding_id');
        });
    }
};
