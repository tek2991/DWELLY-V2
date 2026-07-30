<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            $table->char('audit_id', 26)->nullable()->after('property_id');
            $table->string('apdcl_consumer_id')->nullable()->after('special_terms');
            $table->text('security_deposit_notes')->nullable()->after('security_deposit');
            $table->json('tenant_bank_details')->nullable()->after('special_terms');
            $table->dateTime('signed_at')->nullable()->after('status');
            $table->boolean('signed_by_tenant')->default(false)->after('signed_at');
            $table->boolean('keys_handed_over')->default(false)->after('signed_by_tenant');
            $table->dateTime('keys_handed_over_at')->nullable()->after('keys_handed_over');
            $table->text('key_handover_notes')->nullable()->after('keys_handed_over_at');
            $table->json('key_details')->nullable()->after('key_handover_notes');

            $table->foreign('audit_id')->references('id')->on('audits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            $table->dropForeign(['audit_id']);
            $table->dropColumn([
                'audit_id',
                'apdcl_consumer_id',
                'security_deposit_notes',
                'tenant_bank_details',
                'signed_at',
                'signed_by_tenant',
                'keys_handed_over',
                'keys_handed_over_at',
                'key_handover_notes',
                'key_details',
            ]);
        });
    }
};
