<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            if (! Schema::hasColumn('tenancy_agreements', 'agreement_content')) {
                $table->longText('agreement_content')->nullable()->after('version');
            }
            if (! Schema::hasColumn('tenancy_agreements', 'agreement_customized_at')) {
                $table->timestamp('agreement_customized_at')->nullable()->after('agreement_content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenancy_agreements', function (Blueprint $table) {
            if (Schema::hasColumn('tenancy_agreements', 'agreement_customized_at')) {
                $table->dropColumn('agreement_customized_at');
            }
            if (Schema::hasColumn('tenancy_agreements', 'agreement_content')) {
                $table->dropColumn('agreement_content');
            }
        });
    }
};
