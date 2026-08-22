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
            $table->string('video_status')->nullable()->after('notes');
            $table->text('video_rejection_reason')->nullable()->after('video_status');
            $table->string('video_rejection_type')->nullable()->after('video_rejection_reason');
            $table->timestamp('video_reviewed_at')->nullable()->after('video_rejection_type');
            $table->foreignId('video_reviewed_by_id')->nullable()->after('video_reviewed_at')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropForeign(['video_reviewed_by_id']);
            $table->dropColumn([
                'video_status',
                'video_rejection_reason',
                'video_rejection_type',
                'video_reviewed_at',
                'video_reviewed_by_id',
            ]);
        });
    }
};
