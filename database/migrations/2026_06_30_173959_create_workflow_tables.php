<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_definitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('entity_type'); // e.g. Property, TenancyAgreement
            $table->string('version')->default('1.0');
            $table->boolean('is_active')->default(true);
            $table->json('schema_json'); // Defines states, transitions, required approvals
            $table->timestamps();
        });

        Schema::create('workflow_instances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('workflow_definition_id', 26);
            $table->ulidMorphs('subject'); // e.g. Property_id
            $table->string('current_state');
            $table->json('state_data')->nullable(); // Any data collected during the workflow
            $table->string('status'); // 'in_progress', 'completed', 'cancelled'
            $table->timestamps();

            $table->foreign('workflow_definition_id')->references('id')->on('workflow_definitions')->restrictOnDelete();
        });

        Schema::create('approval_steps', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('workflow_instance_id', 26);
            $table->char('approval_step_type_id', 26); // reference data
            $table->string('required_role')->nullable();
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending'); // pending, approved, rejected
            $table->foreignId('actioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('actioned_at')->nullable();
            $table->text('comments')->nullable();
            $table->timestamps();

            $table->foreign('workflow_instance_id')->references('id')->on('workflow_instances')->cascadeOnDelete();
            $table->foreign('approval_step_type_id')->references('id')->on('approval_step_types')->restrictOnDelete();
        });

        Schema::create('workflow_transitions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('workflow_instance_id', 26);
            $table->string('from_state');
            $table->string('to_state');
            $table->foreignId('transitioned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->foreign('workflow_instance_id')->references('id')->on('workflow_instances')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('approval_steps');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_definitions');
    }
};
