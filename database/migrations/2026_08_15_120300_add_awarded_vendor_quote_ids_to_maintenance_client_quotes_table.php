<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_client_quotes', function (Blueprint $table) {
            $table->json('awarded_vendor_quote_ids')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_client_quotes', function (Blueprint $table) {
            $table->dropColumn('awarded_vendor_quote_ids');
        });
    }
};
