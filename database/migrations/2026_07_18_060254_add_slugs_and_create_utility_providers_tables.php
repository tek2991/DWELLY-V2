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
        // Add slug to opportunity_sources
        if (!Schema::hasColumn('opportunity_sources', 'slug')) {
            Schema::table('opportunity_sources', function (Blueprint $table) {
                $table->string('slug')->unique()->nullable()->after('name');
            });
            // Populate slugs
            $sources = \Illuminate\Support\Facades\DB::table('opportunity_sources')->get();
            foreach ($sources as $source) {
                \Illuminate\Support\Facades\DB::table('opportunity_sources')->where('id', $source->id)->update(['slug' => \Illuminate\Support\Str::slug($source->name)]);
            }
        }

        // Add slug to financial_models
        if (!Schema::hasColumn('financial_models', 'slug')) {
            Schema::table('financial_models', function (Blueprint $table) {
                $table->string('slug')->unique()->nullable()->after('name');
            });
            // Populate slugs
            $models = \Illuminate\Support\Facades\DB::table('financial_models')->get();
            foreach ($models as $model) {
                \Illuminate\Support\Facades\DB::table('financial_models')->where('id', $model->id)->update(['slug' => \Illuminate\Support\Str::slug($model->name)]);
            }
        }

        // Create utility_providers
        if (!Schema::hasTable('utility_providers')) {
            Schema::create('utility_providers', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->char('utility_type_id', 26);
                $table->string('name');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->foreign('utility_type_id')->references('id')->on('utility_types')->cascadeOnDelete();
            });
        }

        // Update mou legal_terms JSON apdcl_consumer_id to electricity_consumer_id
        $mous = \Illuminate\Support\Facades\DB::table('mous')->get();
        foreach ($mous as $mou) {
            if ($mou->legal_terms) {
                $legalTerms = json_decode($mou->legal_terms, true);
                if (isset($legalTerms['apdcl_consumer_id'])) {
                    $legalTerms['electricity_consumer_id'] = $legalTerms['apdcl_consumer_id'];
                    unset($legalTerms['apdcl_consumer_id']);
                    \Illuminate\Support\Facades\DB::table('mous')->where('id', $mou->id)->update(['legal_terms' => json_encode($legalTerms)]);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('utility_providers');
        
        if (Schema::hasColumn('opportunity_sources', 'slug')) {
            Schema::table('opportunity_sources', function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }
        
        if (Schema::hasColumn('financial_models', 'slug')) {
            Schema::table('financial_models', function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }
    }
};
