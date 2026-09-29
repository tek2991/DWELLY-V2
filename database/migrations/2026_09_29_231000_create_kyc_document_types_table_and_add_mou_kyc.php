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
        Schema::create('kyc_document_types', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('has_number')->default(false);
            $table->string('number_label')->nullable();
            $table->string('number_regex')->nullable();
            $table->string('mou_owner_requirement')->default('disabled');
            $table->string('mou_signatory_requirement')->default('disabled');
            $table->string('tenancy_primary_requirement')->default('disabled');
            $table->string('tenancy_secondary_requirement')->default('disabled');
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        if (Schema::hasTable('mous') && ! Schema::hasColumn('mous', 'kyc_documents')) {
            Schema::table('mous', function (Blueprint $table) {
                $table->json('kyc_documents')->nullable()->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mous') && Schema::hasColumn('mous', 'kyc_documents')) {
            Schema::table('mous', function (Blueprint $table) {
                $table->dropColumn('kyc_documents');
            });
        }

        Schema::dropIfExists('kyc_document_types');
    }
};
