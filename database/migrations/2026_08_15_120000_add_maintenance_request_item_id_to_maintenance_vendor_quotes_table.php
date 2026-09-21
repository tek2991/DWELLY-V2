<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_vendor_quotes', function (Blueprint $table) {
            $table->char('maintenance_request_item_id', 26)->nullable()->after('maintenance_request_id');
            $table->foreign('maintenance_request_item_id', 'mvq_mri_id_fk')->references('id')->on('maintenance_request_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_vendor_quotes', function (Blueprint $table) {
            $table->dropForeign('mvq_mri_id_fk');
            $table->dropColumn('maintenance_request_item_id');
        });
    }
};
