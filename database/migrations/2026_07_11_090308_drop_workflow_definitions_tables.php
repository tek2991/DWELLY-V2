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
        Schema::dropIfExists('workflow_transitions');
        Schema::dropIfExists('workflow_instances');
        Schema::dropIfExists('workflow_definitions');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No down migration
    }
};
