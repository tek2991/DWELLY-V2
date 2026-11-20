<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('public_inquiries', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('type')->default('general'); // general, booking, rent_out
            $table->string('name');
            $table->string('phone');
            $table->string('email')->nullable();
            $table->text('message')->nullable();
            $table->char('property_id', 26)->nullable();
            $table->json('payload')->nullable();
            $table->string('status')->default('new'); // new, contacted, converted, closed
            $table->timestamps();

            $table->foreign('property_id')->references('id')->on('properties')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('public_inquiries');
    }
};
