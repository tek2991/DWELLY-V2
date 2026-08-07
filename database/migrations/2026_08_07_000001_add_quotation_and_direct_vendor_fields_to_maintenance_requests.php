<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->boolean('is_direct_vendor')->default(false)->after('payer_type');
            $table->decimal('quotation_amount', 12, 2)->nullable()->after('is_direct_vendor');
            $table->string('quotation_status')->default('pending')->after('quotation_amount');
            $table->text('quotation_notes')->nullable()->after('quotation_status');
            $table->timestamp('quotation_approved_at')->nullable()->after('quotation_notes');
            $table->text('quotation_approval_notes')->nullable()->after('quotation_approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn([
                'is_direct_vendor',
                'quotation_amount',
                'quotation_status',
                'quotation_notes',
                'quotation_approved_at',
                'quotation_approval_notes',
            ]);
        });
    }
};
