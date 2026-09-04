<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->string('category')->default('field_work');
            $table->string('default_priority')->default('medium');
            $table->unsignedInteger('default_sla_hours')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('task_template_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('task_template_id', 26);
            $table->string('title');
            $table->text('description')->nullable();
            $table->boolean('is_mandatory')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('task_template_id')->references('id')->on('task_templates')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_template_items');
        Schema::dropIfExists('task_templates');
    }
};
