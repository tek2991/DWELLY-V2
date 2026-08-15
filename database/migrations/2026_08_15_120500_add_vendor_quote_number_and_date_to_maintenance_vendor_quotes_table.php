<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_vendor_quotes', function (Blueprint $table) {
            $table->string('vendor_quote_number')->nullable()->after('trade_title');
            $table->date('vendor_quote_date')->nullable()->after('vendor_quote_number');
            $table->timestamp('work_order_issued_at')->nullable()->after('work_order_number');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_vendor_quotes', function (Blueprint $table) {
            $table->dropColumn(['vendor_quote_number', 'vendor_quote_date', 'work_order_issued_at']);
        });
    }
};
