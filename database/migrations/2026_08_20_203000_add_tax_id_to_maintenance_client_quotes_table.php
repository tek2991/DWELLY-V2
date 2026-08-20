<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_client_quotes', function (Blueprint $table) {
            $table->unsignedBigInteger('tax_id')->nullable()->after('margin_amount');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_client_quotes', function (Blueprint $table) {
            $table->dropColumn('tax_id');
        });
    }
};
