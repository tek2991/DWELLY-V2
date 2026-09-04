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

        Schema::table('owner_payouts', function (Blueprint $table) use ($prefix) {
            if (! Schema::hasColumn('owner_payouts', 'commission_invoice_id')) {
                $table->foreignId('commission_invoice_id')
                    ->nullable()
                    ->after('transaction_id')
                    ->constrained("{$prefix}invoices")
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('owner_payouts', 'document_snapshot')) {
                $table->json('document_snapshot')->nullable()->after('notes');
            }
            if (! Schema::hasColumn('owner_payouts', 'pdf_path')) {
                $table->string('pdf_path')->nullable()->after('document_snapshot');
            }
            if (! Schema::hasColumn('owner_payouts', 'pdf_generated_at')) {
                $table->timestamp('pdf_generated_at')->nullable()->after('pdf_path');
            }
            if (! Schema::hasColumn('owner_payouts', 'pdf_checksum')) {
                $table->string('pdf_checksum', 64)->nullable()->after('pdf_generated_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('owner_payouts', function (Blueprint $table) {
            if (Schema::hasColumn('owner_payouts', 'pdf_checksum')) {
                $table->dropColumn('pdf_checksum');
            }
            if (Schema::hasColumn('owner_payouts', 'pdf_generated_at')) {
                $table->dropColumn('pdf_generated_at');
            }
            if (Schema::hasColumn('owner_payouts', 'pdf_path')) {
                $table->dropColumn('pdf_path');
            }
            if (Schema::hasColumn('owner_payouts', 'document_snapshot')) {
                $table->dropColumn('document_snapshot');
            }
            if (Schema::hasColumn('owner_payouts', 'commission_invoice_id')) {
                $table->dropConstrainedForeignId('commission_invoice_id');
            }
        });
    }
};
