<?php

use App\Domain\Auth\Enums\RoleName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('name');
            $table->text('description')->nullable()->after('display_name');
            $table->boolean('is_system')->default(false)->after('description')->index();
        });

        // Initialize core system roles metadata
        foreach (RoleName::cases() as $roleEnum) {
            DB::table('roles')
                ->where('name', $roleEnum->value)
                ->update([
                    'display_name' => $roleEnum->label(),
                    'description' => $roleEnum->description(),
                    'is_system' => true,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'description', 'is_system']);
        });
    }
};
