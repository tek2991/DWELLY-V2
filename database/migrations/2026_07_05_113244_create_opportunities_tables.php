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
        Schema::create('opportunities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('number')->unique();
            $table->string('title');
            $table->string('status');
            $table->string('mou_status')->nullable();
            
            $table->foreignUlid('lead_origin_id')->nullable()->constrained('lead_origins');
            $table->foreignUlid('opportunity_source_id')->nullable()->constrained('opportunity_sources');
            
            $table->foreignUlid('assigned_user_id')->nullable()->constrained('users');
            
            $table->foreignUlid('party_id')->constrained('parties');
            
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('landmark')->nullable();
            
            $table->foreignUlid('estimated_property_type_id')->nullable()->constrained('property_types');
            
            $table->string('estimated_bhk')->nullable();
            $table->integer('estimated_size')->nullable();
            $table->boolean('estimated_is_furnished')->nullable();
            $table->decimal('expected_rent', 15, 2)->nullable();
            
            $table->foreignUlid('expected_financial_model_id')->nullable()->constrained('financial_models');
            
            $table->text('internal_summary')->nullable();
            $table->date('expected_onboarding_date')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('opportunity_activities', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->string('activity_type');
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignUlid('performed_by')->nullable()->constrained('users');
            $table->timestamp('performed_at');
            $table->timestamps();
        });

        Schema::create('opportunity_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->json('snapshot_data');
            $table->timestamp('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('opportunity_snapshots');
        Schema::dropIfExists('opportunity_activities');
        Schema::dropIfExists('opportunities');
    }
};
