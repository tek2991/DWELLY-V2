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

        Schema::create("{$prefix}gst_registrations", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->string('gstin')->unique();
            $table->string('legal_name')->nullable();
            $table->string('trade_name')->nullable();
            $table->foreignId('state_id')->constrained("{$prefix}states")->restrictOnDelete();
            $table->text('address')->nullable();
            $table->date('registration_date')->nullable();
            $table->boolean('is_default')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}gst_registrations");
    }
};
