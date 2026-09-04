<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('task_number')->unique();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->char('property_id', 26);
            
            // Polymorphic link to associated domain records (e.g. Agreement, Deboarding, Party, Opportunity, Maintenance)
            $table->nullableMorphs('taskable');
            
            $table->char('template_id', 26)->nullable();
            $table->string('category')->default('field_work'); // TaskCategory enum
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('priority')->default('medium'); // TaskPriority enum
            $table->string('status')->default('pending'); // TaskStatus enum
            
            // Scheduling & SLAs
            $table->dateTime('scheduled_at')->nullable();
            $table->dateTime('due_date')->nullable();
            $table->unsignedInteger('sla_hours')->nullable();
            
            // Ownership & Assignment
            $table->foreignId('assigned_to_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            
            // Resolution & Verification
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('completed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('property_id')->references('id')->on('properties')->cascadeOnDelete();
            $table->foreign('template_id')->references('id')->on('task_templates')->nullOnDelete();

            $table->index('property_id');
            $table->index('status');
            $table->index('category');
            $table->index('priority');
            $table->index('due_date');
            $table->index('assigned_to_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
