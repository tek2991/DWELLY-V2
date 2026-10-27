<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tenancy_agreements', 'version')) {
            Schema::table('tenancy_agreements', function (Blueprint $table) {
                $table->integer('version')->default(1)->after('code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenancy_agreements', 'version')) {
            Schema::table('tenancy_agreements', function (Blueprint $table) {
                $table->dropColumn('version');
            });
        }
    }
};
