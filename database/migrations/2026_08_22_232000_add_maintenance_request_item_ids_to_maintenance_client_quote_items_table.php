<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_client_quote_items', function (Blueprint $table) {
            $table->json('maintenance_request_item_ids')->nullable()->after('maintenance_request_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_client_quote_items', function (Blueprint $table) {
            $table->dropColumn('maintenance_request_item_ids');
        });
    }
};
