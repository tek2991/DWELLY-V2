<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_client_quote_items', function (Blueprint $table) {
            $table->char('maintenance_request_item_id', 26)->nullable()->after('vendor_quote_id');
            $table->foreign('maintenance_request_item_id')->references('id')->on('maintenance_request_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_client_quote_items', function (Blueprint $table) {
            $table->dropForeign(['maintenance_request_item_id']);
            $table->dropColumn('maintenance_request_item_id');
        });
    }
};
