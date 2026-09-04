<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function prefix(): string
    {
        return config('accounting.table_prefix', 'acc_');
    }

    public function up(): void
    {
        $prefix = $this->prefix();
        $tableName = "{$prefix}invoices";

        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, function (Blueprint $table) {
                if (! Schema::hasColumn($table->getTable(), 'document_snapshot')) {
                    $table->json('document_snapshot')->nullable()->after('billing_address_snapshot');
                }
                if (! Schema::hasColumn($table->getTable(), 'pdf_path')) {
                    $table->string('pdf_path')->nullable()->after('document_snapshot');
                }
                if (! Schema::hasColumn($table->getTable(), 'pdf_generated_at')) {
                    $table->timestamp('pdf_generated_at')->nullable()->after('pdf_path');
                }
                if (! Schema::hasColumn($table->getTable(), 'pdf_checksum')) {
                    $table->string('pdf_checksum', 64)->nullable()->after('pdf_generated_at');
                }
            });
        }
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        $tableName = "{$prefix}invoices";

        if (Schema::hasTable($tableName)) {
            Schema::table($tableName, function (Blueprint $table) {
                if (Schema::hasColumn($table->getTable(), 'pdf_checksum')) {
                    $table->dropColumn('pdf_checksum');
                }
                if (Schema::hasColumn($table->getTable(), 'pdf_generated_at')) {
                    $table->dropColumn('pdf_generated_at');
                }
                if (Schema::hasColumn($table->getTable(), 'pdf_path')) {
                    $table->dropColumn('pdf_path');
                }
                if (Schema::hasColumn($table->getTable(), 'document_snapshot')) {
                    $table->dropColumn('document_snapshot');
                }
            });
        }
    }
};
