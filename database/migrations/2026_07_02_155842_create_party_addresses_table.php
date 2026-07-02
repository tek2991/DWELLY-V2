<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('party_addresses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->char('party_id', 26);
            $table->string('type')->default('residential'); // residential, registered_office, billing, correspondence
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
            $table->string('country')->nullable()->default('India');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('party_id')->references('id')->on('parties')->cascadeOnDelete();
        });

        Schema::table('party_individuals', function (Blueprint $table) {
            $table->dropColumn(['address_line_1', 'address_line_2', 'city', 'state', 'pincode']);
        });

        Schema::table('party_organizations', function (Blueprint $table) {
            $table->dropColumn(['registered_address']);
        });
    }

    public function down(): void
    {
        Schema::table('party_organizations', function (Blueprint $table) {
            $table->text('registered_address')->nullable();
        });

        Schema::table('party_individuals', function (Blueprint $table) {
            $table->string('address_line_1')->nullable();
            $table->string('address_line_2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode')->nullable();
        });

        Schema::dropIfExists('party_addresses');
    }
};
