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
                if (! Schema::hasColumn($table->getTable(), 'billing_period_start')) {
                    $table->date('billing_period_start')->nullable()->after('due_date');
                }
                if (! Schema::hasColumn($table->getTable(), 'billing_period_end')) {
                    $table->date('billing_period_end')->nullable()->after('billing_period_start');
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
                if (Schema::hasColumn($table->getTable(), 'billing_period_end')) {
                    $table->dropColumn('billing_period_end');
                }
                if (Schema::hasColumn($table->getTable(), 'billing_period_start')) {
                    $table->dropColumn('billing_period_start');
                }
            });
        }
    }
};
