<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            $table->timestamp('deboarded_at')->nullable()->after('keys_handed_over_at');
            $table->date('vacating_date')->nullable()->after('deboarded_at');
            $table->date('notice_date')->nullable()->after('vacating_date');
            $table->string('deboarding_reason')->nullable()->after('notice_date');
            $table->text('deboarding_notes')->nullable()->after('deboarding_reason');
            $table->char('move_out_audit_id', 26)->nullable()->after('deboarding_notes');
            $table->boolean('keys_returned')->default(false)->after('move_out_audit_id');
            $table->timestamp('keys_returned_at')->nullable()->after('keys_returned');
            $table->json('deposit_deductions_breakdown')->nullable()->after('keys_returned_at');
            $table->decimal('net_deposit_refund', 12, 2)->default(0.00)->after('deposit_deductions_breakdown');
            $table->string('deposit_settlement_status')->nullable()->after('net_deposit_refund');

            $table->foreign('move_out_audit_id')->references('id')->on('audits')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            $table->dropForeign(['move_out_audit_id']);
            $table->dropColumn([
                'deboarded_at',
                'vacating_date',
                'notice_date',
                'deboarding_reason',
                'deboarding_notes',
                'move_out_audit_id',
                'keys_returned',
                'keys_returned_at',
                'deposit_deductions_breakdown',
                'net_deposit_refund',
                'deposit_settlement_status',
            ]);
        });
    }
};
