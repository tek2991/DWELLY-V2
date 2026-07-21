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
        Schema::table('mous', function (Blueprint $table) {
            $table->dropColumn([
                'signatory_name',
                'signatory_phone',
                'signatory_email',
                'signatory_aadhar_number',
                'signatory_pan_number',
                'signatory_relation',
            ]);
            
            $table->json('signatory_details')->nullable()->after('is_signatory_different');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mous', function (Blueprint $table) {
            $table->dropColumn('signatory_details');
            
            $table->string('signatory_name')->nullable()->after('is_signatory_different');
            $table->string('signatory_phone')->nullable()->after('signatory_name');
            $table->string('signatory_email')->nullable()->after('signatory_phone');
            $table->string('signatory_aadhar_number')->nullable()->after('signatory_email');
            $table->string('signatory_pan_number')->nullable()->after('signatory_aadhar_number');
            $table->string('signatory_relation')->nullable()->after('signatory_pan_number');
        });
    }
};
