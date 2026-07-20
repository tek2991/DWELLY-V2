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
        Schema::create('audit_item_reviews', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('audit_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->integer('review_round');
            $table->string('comment_type')->nullable(); // GENERAL, PHOTO, CONDITION, ANNOTATION, OTHER
            $table->string('status'); // approved, rejected
            $table->text('comments')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_item_reviews');
    }
};
