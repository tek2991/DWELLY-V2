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

        Schema::table('tenancy_agreements', function (Blueprint $table) use ($prefix) {
            if (! Schema::hasColumn('tenancy_agreements', 'previous_agreement_id')) {
                $table->char('previous_agreement_id', 26)
                    ->nullable()
                    ->after('pricing_version_id');
                $table->foreign('previous_agreement_id')
                    ->references('id')
                    ->on('tenancy_agreements')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tenancy_agreements', 'is_renewal')) {
                $table->boolean('is_renewal')
                    ->default(false)
                    ->after('previous_agreement_id');
            }

            if (! Schema::hasColumn('tenancy_agreements', 'renewal_notes')) {
                $table->text('renewal_notes')
                    ->nullable()
                    ->after('is_renewal');
            }

            if (! Schema::hasColumn('tenancy_agreements', 'documentation_charge')) {
                $table->decimal('documentation_charge', 12, 2)
                    ->default(1500.00)
                    ->after('rent_amount');
            }

            if (! Schema::hasColumn('tenancy_agreements', 'documentation_invoice_id')) {
                $table->foreignId('documentation_invoice_id')
                    ->nullable()
                    ->after('documentation_charge')
                    ->constrained("{$prefix}invoices")
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            if (Schema::hasColumn('tenancy_agreements', 'documentation_invoice_id')) {
                $table->dropConstrainedForeignId('documentation_invoice_id');
            }

            if (Schema::hasColumn('tenancy_agreements', 'previous_agreement_id')) {
                $table->dropForeign(['previous_agreement_id']);
                $table->dropColumn('previous_agreement_id');
            }

            $columnsToDrop = [];
            foreach (['is_renewal', 'renewal_notes', 'documentation_charge'] as $col) {
                if (Schema::hasColumn('tenancy_agreements', $col)) {
                    $columnsToDrop[] = $col;
                }
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
