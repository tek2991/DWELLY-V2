<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_client_quote_items', function (Blueprint $table) {
            $table->decimal('vendor_cost', 12, 2)->default(0.00)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_client_quote_items', function (Blueprint $table) {
            $table->decimal('vendor_cost', 12, 2)->default(0.00)->nullable(false)->change();
        });
    }
};
