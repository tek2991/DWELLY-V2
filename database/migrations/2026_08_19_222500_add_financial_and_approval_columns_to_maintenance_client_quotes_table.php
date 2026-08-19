<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_client_quotes', function (Blueprint $table) {
            $table->decimal('subtotal_amount', 12, 2)->default(0.00)->after('total_amount');
            $table->decimal('margin_percentage', 5, 2)->nullable()->after('subtotal_amount');
            $table->decimal('margin_amount', 12, 2)->default(0.00)->after('margin_percentage');
            $table->decimal('gst_percentage', 5, 2)->nullable()->after('margin_amount');
            $table->decimal('tax_amount', 12, 2)->default(0.00)->after('gst_percentage');
            $table->date('valid_until')->nullable()->after('tax_amount');
            $table->string('approved_by_type', 50)->nullable()->after('approval_notes');
            $table->string('approval_channel', 50)->nullable()->after('approved_by_type');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_client_quotes', function (Blueprint $table) {
            $table->dropColumn([
                'subtotal_amount',
                'margin_percentage',
                'margin_amount',
                'gst_percentage',
                'tax_amount',
                'valid_until',
                'approved_by_type',
                'approval_channel',
            ]);
        });
    }
};
