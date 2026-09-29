<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('kyc_document_types') && ! Schema::hasColumn('kyc_document_types', 'icon')) {
            Schema::table('kyc_document_types', function (Blueprint $table) {
                $table->string('icon')->nullable()->default('heroicon-m-document-text')->after('description');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('kyc_document_types') && Schema::hasColumn('kyc_document_types', 'icon')) {
            Schema::table('kyc_document_types', function (Blueprint $table) {
                $table->dropColumn('icon');
            });
        }
    }
};
