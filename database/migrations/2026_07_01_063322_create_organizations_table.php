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

        Schema::create("{$prefix}organizations", function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('legal_name')->nullable();
            $table->string('trade_name')->nullable();
            $table->string('pan')->nullable();
            $table->string('default_currency')->default('INR');
            $table->integer('fiscal_year_start')->default(4); // April
            $table->string('tax_regime')->default('generic');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->string('logo')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}organizations");
    }
};
