<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_client_quotes', function (Blueprint $table) {
            $table->unsignedInteger('version')->default(1)->after('quote_number');
            $table->timestamp('generated_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_client_quotes', function (Blueprint $table) {
            $table->dropColumn(['version', 'generated_at']);
        });
    }
};
