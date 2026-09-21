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

        Schema::create("{$prefix}states", function (Blueprint $table) {
            $table->id();
            $table->string('country_id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->string('gst_state_code', 2)->nullable();
            $table->boolean('is_union_territory')->default(false);
            $table->timestamps();
        });

        if (Schema::hasTable('parties') && Schema::hasColumn('parties', 'state_id')) {
            Schema::table('parties', function (Blueprint $table) use ($prefix) {
                $table->foreign('state_id')->references('id')->on("{$prefix}states")->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $prefix = $this->prefix();

        if (Schema::hasTable('parties') && Schema::hasColumn('parties', 'state_id')) {
            Schema::table('parties', function (Blueprint $table) {
                $table->dropForeign(['state_id']);
            });
        }

        Schema::dropIfExists("{$prefix}states");
    }
};
