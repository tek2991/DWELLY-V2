<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->string('onboarding_status')->default('draft')->after('is_preferred');
            $table->text('verification_notes')->nullable()->after('onboarding_status');
            $table->timestamp('verified_at')->nullable()->after('verification_notes');
            $table->foreignId('verified_by_id')->nullable()->constrained('users')->nullOnDelete()->after('verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->dropForeign(['verified_by_id']);
            $table->dropColumn([
                'onboarding_status',
                'verification_notes',
                'verified_at',
                'verified_by_id',
            ]);
        });
    }
};
