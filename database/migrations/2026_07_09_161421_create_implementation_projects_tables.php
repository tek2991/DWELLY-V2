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
        Schema::create('implementation_projects', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('workflow_template_id', 26);
            $table->string('entity_type'); // e.g. App\Domain\Property\Models\Property
            $table->char('entity_id', 26);
            $table->string('status')->default('created'); // created, in_progress, on_hold, completed, cancelled
            $table->date('target_completion_date')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('progress', 5, 2)->default(0); // 0.00 to 100.00
            $table->timestamps();
            
            $table->foreign('workflow_template_id')->references('id')->on('workflow_templates')->restrictOnDelete();
            $table->index(['entity_type', 'entity_id']);
        });

        Schema::create('implementation_project_variables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('project_id', 26);
            $table->string('key');
            $table->text('value')->nullable();
            $table->string('type')->default('string');
            $table->timestamps();
            
            $table->foreign('project_id')->references('id')->on('implementation_projects')->cascadeOnDelete();
        });

        Schema::create('implementation_project_phases', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('project_id', 26);
            $table->string('name');
            $table->integer('order')->default(0);
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('implementation_projects')->cascadeOnDelete();
        });

        Schema::create('implementation_project_stages', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('project_id', 26);
            $table->char('phase_id', 26)->nullable();
            $table->string('name');
            $table->integer('order')->default(0);
            $table->string('status')->default('not_started'); // not_started, active, completed, skipped
            $table->boolean('is_mandatory')->default(true);
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('implementation_projects')->cascadeOnDelete();
            $table->foreign('phase_id')->references('id')->on('implementation_project_phases')->nullOnDelete();
        });

        Schema::create('implementation_deliverables', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('project_id', 26);
            $table->char('work_package_id', 26)->nullable(); // Optional reference to the master work package id if needed, or just a snapshot name
            $table->string('name');
            $table->string('provider_key');
            $table->json('validation_parameters')->nullable();
            $table->string('status')->default('pending'); // pending, submitted, verified, rejected
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('implementation_projects')->cascadeOnDelete();
        });

        Schema::create('implementation_tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('project_id', 26);
            $table->char('task_template_id', 26)->nullable(); // Ref to template if needed
            $table->char('stage_id', 26);
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('open'); // open, in_progress, blocked, completed
            $table->integer('weight')->default(1);
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('implementation_projects')->cascadeOnDelete();
            $table->foreign('stage_id')->references('id')->on('implementation_project_stages')->cascadeOnDelete();
        });

        Schema::create('implementation_task_checklists', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('task_id', 26);
            $table->string('item_text');
            $table->boolean('is_mandatory')->default(true);
            $table->boolean('is_completed')->default(false);
            $table->timestamps();

            $table->foreign('task_id')->references('id')->on('implementation_tasks')->cascadeOnDelete();
        });

        Schema::create('implementation_task_dependencies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('task_id', 26);
            $table->char('depends_on_task_id', 26);
            $table->timestamps();

            $table->foreign('task_id', 'itd_task_fk')->references('id')->on('implementation_tasks')->cascadeOnDelete();
            $table->foreign('depends_on_task_id', 'itd_depends_fk')->references('id')->on('implementation_tasks')->cascadeOnDelete();
        });

        Schema::create('implementation_approval_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('project_id', 26);
            $table->integer('step_order');
            $table->string('required_role');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('actioned_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('implementation_projects')->cascadeOnDelete();
        });

        Schema::create('implementation_findings', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('project_id', 26);
            $table->string('related_type')->nullable(); // e.g. task, deliverable
            $table->char('related_id', 26)->nullable();
            $table->string('severity')->default('observation'); // critical, high, medium, low, observation
            $table->text('description');
            $table->string('status')->default('open'); // open, resolved
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on('implementation_projects')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('implementation_findings');
        Schema::dropIfExists('implementation_approval_steps');
        Schema::dropIfExists('implementation_task_dependencies');
        Schema::dropIfExists('implementation_task_checklists');
        Schema::dropIfExists('implementation_tasks');
        Schema::dropIfExists('implementation_deliverables');
        Schema::dropIfExists('implementation_project_stages');
        Schema::dropIfExists('implementation_project_phases');
        Schema::dropIfExists('implementation_project_variables');
        Schema::dropIfExists('implementation_projects');
    }
};
