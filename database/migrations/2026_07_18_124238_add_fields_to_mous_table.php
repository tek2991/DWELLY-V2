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
        Schema::table('mous', function (Blueprint $table) {
            $table->date('start_date')->nullable()->after('version');
            $table->foreignId('signatory_party_id')->nullable()->after('party_id')->constrained('parties')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mous', function (Blueprint $table) {
            $table->dropForeign(['signatory_party_id']);
            $table->dropColumn(['start_date', 'signatory_party_id']);
        });
    }
};
