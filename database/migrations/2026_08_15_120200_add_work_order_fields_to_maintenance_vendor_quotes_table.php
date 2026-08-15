<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_vendor_quotes', function (Blueprint $table) {
            $table->boolean('is_awarded')->default(false)->after('status');
            $table->string('work_order_number')->nullable()->after('is_awarded');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_vendor_quotes', function (Blueprint $table) {
            $table->dropColumn(['is_awarded', 'work_order_number']);
        });
    }
};
