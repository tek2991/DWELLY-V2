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
        Schema::table('onboarding_projects', function (Blueprint $table) {
            $table->foreignId('reviewer_id')->nullable()->after('assigned_executive_id')->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable()->after('reviewer_id');
            $table->timestamp('reviewed_at')->nullable()->after('submitted_at');
            $table->text('review_notes')->nullable()->after('reviewed_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onboarding_projects', function (Blueprint $table) {
            $table->dropForeign(['reviewer_id']);
            $table->dropColumn(['reviewer_id', 'submitted_at', 'reviewed_at', 'review_notes']);
        });
    }
};
