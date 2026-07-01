<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected function prefix(): string
    {
        return config('accounting.table_prefix', 'acc_');
    }

    public function up(): void
    {
        $prefix = $this->prefix();

        Schema::create("{$prefix}fiscal_period_events", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('fiscal_period_id')->constrained("{$prefix}fiscal_periods")->cascadeOnDelete();
            
            $table->string('event_type', 50); // 'closed', 'reopened'
            
            $table->foreignId('performed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('performed_at');
            
            $table->json('metadata')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}fiscal_period_events");
    }
};
