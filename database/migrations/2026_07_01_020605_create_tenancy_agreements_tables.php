<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenancy_agreements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('property_id', 26);
            $table->string('code')->unique(); // e.g. TNC-2026-0001
            $table->string('status'); // draft, active, terminated, etc.
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->decimal('rent_amount', 12, 2);
            $table->decimal('security_deposit', 12, 2);
            $table->integer('lock_in_period_months')->default(0);
            $table->integer('notice_period_days')->default(30);
            $table->text('special_terms')->nullable();
            $table->char('pricing_version_id', 26)->nullable();
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->restrictOnDelete();
            $table->foreign('pricing_version_id')->references('id')->on('property_pricing_versions')->nullOnDelete();
        });

        Schema::create('tenancy_roles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenancy_agreement_id', 26);
            $table->char('party_id', 26);
            $table->string('role_type'); // Primary Tenant, Co-Tenant, Guarantor
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('tenancy_agreement_id')->references('id')->on('tenancy_agreements')->cascadeOnDelete();
            $table->foreign('party_id')->references('id')->on('parties')->restrictOnDelete();
        });

        Schema::create('tenant_risk_snapshots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('tenancy_role_id', 26);
            $table->integer('credit_score')->nullable();
            $table->boolean('background_verified')->default(false);
            $table->string('employment_status')->nullable();
            $table->decimal('monthly_income', 12, 2)->nullable();
            $table->text('screening_notes')->nullable();
            $table->json('raw_report_data')->nullable(); // From external API if any
            $table->timestamps();

            $table->foreign('tenancy_role_id')->references('id')->on('tenancy_roles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_risk_snapshots');
        Schema::dropIfExists('tenancy_roles');
        Schema::dropIfExists('tenancy_agreements');
    }
};
