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

        Schema::table("{$prefix}transactions", function (Blueprint $table) {
            $table->string('document_path')->nullable()->after('reference');
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();

        Schema::table("{$prefix}transactions", function (Blueprint $table) {
            $table->dropColumn('document_path');
        });
    }
};
