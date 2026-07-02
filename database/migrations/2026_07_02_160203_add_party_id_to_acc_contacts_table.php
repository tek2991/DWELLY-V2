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
        Schema::table("{$prefix}contacts", function (Blueprint $table) {
            $table->char('party_id', 26)->nullable()->after('id');
            $table->foreign('party_id')->references('id')->on('parties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::table("{$prefix}contacts", function (Blueprint $table) {
            $table->dropForeign(['party_id']);
            $table->dropColumn('party_id');
        });
    }
};
