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
        Schema::table('utility_providers', function (Blueprint $table) {
            $table->string('slug')->unique()->nullable()->after('name');
        });
        
        // Populate slugs for existing ones
        $providers = \Illuminate\Support\Facades\DB::table('utility_providers')->get();
        foreach ($providers as $provider) {
            \Illuminate\Support\Facades\DB::table('utility_providers')->where('id', $provider->id)->update(['slug' => \Illuminate\Support\Str::slug($provider->name)]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('utility_providers', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
