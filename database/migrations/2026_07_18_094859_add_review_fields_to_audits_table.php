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
        Schema::table('audits', function (Blueprint $table) {
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->integer('review_round')->default(1);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('review_started_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->dropColumn(['reviewer_id', 'review_round', 'submitted_at', 'review_started_at']);
        });
    }
};
