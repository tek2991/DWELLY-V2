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
        Schema::create('workflow_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('type');
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->integer('estimated_duration_days')->nullable();
            $table->timestamps();
        });

        Schema::create('workflow_variables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('workflow_template_id', 26);
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
            
            $table->foreign('workflow_template_id')->references('id')->on('workflow_templates')->cascadeOnDelete();
        });

        Schema::create('workflow_phases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('workflow_template_id', 26);
            $table->string('name');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('workflow_template_id')->references('id')->on('workflow_templates')->cascadeOnDelete();
        });

        Schema::create('workflow_stages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('workflow_template_id', 26);
            $table->char('phase_id', 26)->nullable();
            $table->string('name');
            $table->integer('order')->default(0);
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->foreign('workflow_template_id')->references('id')->on('workflow_templates')->cascadeOnDelete();
            $table->foreign('phase_id')->references('id')->on('workflow_phases')->nullOnDelete();
        });

        Schema::create('workflow_work_packages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('workflow_template_id', 26);
            $table->string('name');
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->foreign('workflow_template_id')->references('id')->on('workflow_templates')->cascadeOnDelete();
        });

        Schema::create('workflow_deliverables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('workflow_template_id', 26);
            $table->char('work_package_id', 26);
            $table->string('name');
            $table->string('provider_key');
            $table->json('validation_parameters')->nullable();
            $table->timestamps();

            $table->foreign('workflow_template_id')->references('id')->on('workflow_templates')->cascadeOnDelete();
            $table->foreign('work_package_id')->references('id')->on('workflow_work_packages')->cascadeOnDelete();
        });

        Schema::create('workflow_task_templates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('workflow_template_id', 26);
            $table->char('work_package_id', 26);
            $table->char('stage_id', 26);
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium');
            $table->boolean('is_mandatory')->default(true);
            $table->integer('weight')->default(1);
            $table->json('assignment_rule')->nullable();
            $table->timestamps();

            $table->foreign('workflow_template_id')->references('id')->on('workflow_templates')->cascadeOnDelete();
            $table->foreign('work_package_id')->references('id')->on('workflow_work_packages')->cascadeOnDelete();
            $table->foreign('stage_id')->references('id')->on('workflow_stages')->cascadeOnDelete();
        });

        Schema::create('workflow_task_checklists', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('task_template_id', 26);
            $table->string('item_text');
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->foreign('task_template_id')->references('id')->on('workflow_task_templates')->cascadeOnDelete();
        });

        Schema::create('workflow_task_dependencies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('task_template_id', 26);
            $table->char('depends_on_task_template_id', 26);
            $table->timestamps();

            $table->foreign('task_template_id', 'wtd_task_fk')->references('id')->on('workflow_task_templates')->cascadeOnDelete();
            $table->foreign('depends_on_task_template_id', 'wtd_depends_fk')->references('id')->on('workflow_task_templates')->cascadeOnDelete();
        });

        Schema::create('workflow_approval_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('workflow_template_id', 26);
            $table->integer('step_order');
            $table->string('required_role');
            $table->timestamps();

            $table->foreign('workflow_template_id')->references('id')->on('workflow_templates')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workflow_approval_steps');
        Schema::dropIfExists('workflow_task_dependencies');
        Schema::dropIfExists('workflow_task_checklists');
        Schema::dropIfExists('workflow_task_templates');
        Schema::dropIfExists('workflow_deliverables');
        Schema::dropIfExists('workflow_work_packages');
        Schema::dropIfExists('workflow_stages');
        Schema::dropIfExists('workflow_phases');
        Schema::dropIfExists('workflow_variables');
        Schema::dropIfExists('workflow_templates');
    }
};
