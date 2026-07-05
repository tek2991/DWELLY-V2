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
        Schema::table('parties', function (Blueprint $table) {
            $table->boolean('is_tax_registered')->default(false);
            $table->string('gst_registration_type')->nullable();
            $table->foreignId('state_id')->nullable()->constrained('acc_states')->nullOnDelete();
        });

        Schema::table('party_individuals', function (Blueprint $table) {
            $table->string('gstin')->nullable()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('party_individuals', function (Blueprint $table) {
            $table->dropColumn('gstin');
        });

        Schema::table('parties', function (Blueprint $table) {
            $table->dropForeign(['state_id']);
            $table->dropColumn(['is_tax_registered', 'gst_registration_type', 'state_id']);
        });
    }
};
