<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mous', function (Blueprint $table) {
            $table->foreignUlid('property_id')->nullable()->after('opportunity_id')->constrained('properties')->cascadeOnDelete();
            $table->string('type')->default('onboarding')->after('property_id');
        });

        // Migrate existing data
        DB::table('properties')
            ->whereNotNull('mou_id')
            ->orderBy('id')
            ->chunk(100, function ($properties) {
                foreach ($properties as $property) {
                    DB::table('mous')
                        ->where('id', $property->mou_id)
                        ->update([
                            'property_id' => $property->id,
                            'type' => 'onboarding',
                        ]);
                }
            });

        Schema::table('properties', function (Blueprint $table) {
            $table->dropForeign(['mou_id']);
            $table->dropColumn('mou_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->foreignUlid('mou_id')->nullable()->constrained('mous');
        });

        DB::table('mous')
            ->whereNotNull('property_id')
            ->orderBy('id')
            ->chunk(100, function ($mous) {
                foreach ($mous as $mou) {
                    DB::table('properties')
                        ->where('id', $mou->property_id)
                        ->update(['mou_id' => $mou->id]);
                }
            });

        Schema::table('mous', function (Blueprint $table) {
            $table->dropForeign(['property_id']);
            $table->dropColumn(['property_id', 'type']);
        });
    }
};
