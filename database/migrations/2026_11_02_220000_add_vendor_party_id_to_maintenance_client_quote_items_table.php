<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_client_quote_items', function (Blueprint $table) {
            $table->char('vendor_party_id', 26)->nullable()->after('vendor_quote_id');
            $table->foreign('vendor_party_id', 'mcqi_vendor_party_id_fk')->references('id')->on('parties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_client_quote_items', function (Blueprint $table) {
            $table->dropForeign('mcqi_vendor_party_id_fk');
            $table->dropColumn('vendor_party_id');
        });
    }
};

