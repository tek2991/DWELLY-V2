<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            $table->decimal('booking_amount', 12, 2)->default(0.00)->after('security_deposit');
        });
    }

    public function down(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            $table->dropColumn('booking_amount');
        });
    }
};
