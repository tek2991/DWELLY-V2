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

        Schema::create("{$prefix}fiscal_periods", function (Blueprint $table) use ($prefix) {
            $table->id();
            
            $table->string('name', 100)->unique("{$prefix}fp_name_unique");
            $table->date('start_date');
            $table->date('end_date');
            
            $table->string('status', 50)->default('open');
            $table->bigInteger('closing_profit_loss')->nullable();
            
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}fiscal_periods");
    }
};
