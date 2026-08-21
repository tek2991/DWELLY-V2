<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->timestamp('client_accepted_at')->nullable()->after('completed_at');
            $table->string('client_accepted_by_name')->nullable()->after('client_accepted_at');
            $table->text('client_acceptance_notes')->nullable()->after('client_accepted_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_requests', function (Blueprint $table) {
            $table->dropColumn([
                'client_accepted_at',
                'client_accepted_by_name',
                'client_acceptance_notes',
            ]);
        });
    }
};
