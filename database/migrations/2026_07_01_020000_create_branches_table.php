<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create("branches", function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('organization_id');
            $table->foreignId('gst_registration_id')->nullable();
            
            $table->string('name');
            $table->string('code')->unique();
            
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->foreignId('state_id')->nullable();
            $table->string('postal_code')->nullable();
            
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            
            $table->boolean('is_active')->default(true);
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists("branches");
    }
};
