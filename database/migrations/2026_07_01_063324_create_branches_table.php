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

        Schema::create("{$prefix}branches", function (Blueprint $table) use ($prefix) {
            $table->id();
            $table->foreignId('organization_id')->constrained("{$prefix}organizations")->cascadeOnDelete();
            $table->foreignId('gst_registration_id')->nullable()->constrained("{$prefix}gst_registrations")->nullOnDelete();
            
            $table->string('name');
            $table->string('code')->unique();
            
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->foreignId('state_id')->nullable()->constrained("{$prefix}states")->nullOnDelete();
            $table->string('postal_code')->nullable();
            
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $prefix = $this->prefix();
        Schema::dropIfExists("{$prefix}branches");
    }
};
