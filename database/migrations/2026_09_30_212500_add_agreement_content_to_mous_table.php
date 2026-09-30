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
            $table->longText('agreement_content')->nullable()->after('legal_terms');
            $table->timestamp('agreement_customized_at')->nullable()->after('agreement_content');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mous', function (Blueprint $table) {
            $table->dropColumn(['agreement_content', 'agreement_customized_at']);
        });
    }
};
