<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parties', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('party_type'); // 'individual' or 'organization'
            $table->string('display_name');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->string('whatsapp_number')->nullable();
            $table->char('region_id', 26)->nullable();
            $table->string('accounting_contact_id')->nullable();
            $table->timestamps();

            $table->foreign('region_id')->references('id')->on('regions')->nullOnDelete();
        });

        Schema::create('party_individuals', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('party_id', 26)->unique();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable();
            $table->string('aadhaar_number')->nullable()->index();
            $table->string('pan_number')->nullable()->index();
            $table->string('voter_id')->nullable();
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();

            $table->foreign('party_id')->references('id')->on('parties')->cascadeOnDelete();
        });

        Schema::create('party_organizations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('party_id', 26)->unique();
            $table->string('legal_name');
            $table->string('gstin')->nullable()->index();
            $table->string('pan')->nullable()->index();
            $table->string('cin')->nullable();
            $table->text('registered_address')->nullable();
            $table->string('contact_person_name')->nullable();
            $table->string('contact_person_phone')->nullable();

            $table->foreign('party_id')->references('id')->on('parties')->cascadeOnDelete();
        });

        Schema::create('party_bank_accounts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('party_id', 26);
            $table->string('account_name');
            $table->string('account_number');
            $table->string('ifsc_code');
            $table->string('bank_name')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamps();

            $table->foreign('party_id')->references('id')->on('parties')->cascadeOnDelete();
        });

        Schema::create('owner_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('party_id', 26)->unique();
            $table->char('default_bank_account_id', 26)->nullable();
            $table->string('notification_preference')->default('all');
            $table->string('payout_method')->default('per_unit');

            $table->foreign('party_id')->references('id')->on('parties')->cascadeOnDelete();
            $table->foreign('default_bank_account_id')->references('id')->on('party_bank_accounts')->nullOnDelete();
        });

        Schema::create('tenant_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('party_id', 26)->unique();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('occupation')->nullable();
            $table->string('employer_name')->nullable();
            $table->decimal('monthly_income', 12, 2)->nullable();

            $table->foreign('party_id')->references('id')->on('parties')->cascadeOnDelete();
        });

        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('party_id', 26)->unique();
            $table->char('vendor_trade_id', 26);
            $table->string('gstin')->nullable();
            $table->json('service_regions')->nullable();
            $table->decimal('rating', 3, 2)->nullable();
            $table->integer('total_jobs_completed')->default(0);
            $table->boolean('is_preferred')->default(false);

            $table->foreign('party_id')->references('id')->on('parties')->cascadeOnDelete();
            $table->foreign('vendor_trade_id')->references('id')->on('vendor_trades')->cascadeOnDelete();
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('party_id', 26)->unique();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_code')->nullable();
            $table->string('department')->nullable();
            $table->string('designation')->nullable();
            $table->date('joining_date')->nullable();
            $table->foreignId('reporting_to')->nullable()->constrained('users')->nullOnDelete();

            $table->foreign('party_id')->references('id')->on('parties')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_profiles');
        Schema::dropIfExists('vendor_profiles');
        Schema::dropIfExists('tenant_profiles');
        Schema::dropIfExists('owner_profiles');
        Schema::dropIfExists('party_bank_accounts');
        Schema::dropIfExists('party_organizations');
        Schema::dropIfExists('party_individuals');
        Schema::dropIfExists('parties');
    }
};
