<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add branch_id to properties
        if (!Schema::hasColumn('properties', 'branch_id')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            });
        }

        // 2. Add branch_id to opportunities
        if (!Schema::hasColumn('opportunities', 'branch_id')) {
            Schema::table('opportunities', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            });
        }

        // 3. Add branch_id to tenancy_agreements
        if (!Schema::hasColumn('tenancy_agreements', 'branch_id')) {
            Schema::table('tenancy_agreements', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            });
        }

        // 4. Add branch_id to mous
        if (!Schema::hasColumn('mous', 'branch_id')) {
            Schema::table('mous', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            });
        }

        // 5. Add branch_id to maintenance_requests
        if (!Schema::hasColumn('maintenance_requests', 'branch_id')) {
            Schema::table('maintenance_requests', function (Blueprint $table) {
                $table->foreignId('branch_id')->nullable()->after('id')->constrained('branches')->nullOnDelete();
            });
        }

        // 6. Database-agnostic Data Backfill
        $ghyBranchId = DB::table('branches')->where('code', 'GHY')->orWhere('city', 'Guwahati')->value('id') ?? 1;
        $blrBranchId = DB::table('branches')->where('code', 'BLR')->orWhere('city', 'Bangalore')->value('id') ?? 2;

        // Backfill properties based on city
        DB::table('properties')
            ->where('city', 'like', '%Guwahati%')
            ->update(['branch_id' => $ghyBranchId]);

        DB::table('properties')
            ->where('city', 'like', '%Bangalore%')
            ->update(['branch_id' => $blrBranchId]);

        // Default any remaining properties to Guwahati
        DB::table('properties')->whereNull('branch_id')->update(['branch_id' => $ghyBranchId]);

        // Backfill tenancy_agreements, mous, maintenance_requests from properties
        $propertyBranches = DB::table('properties')
            ->whereNotNull('branch_id')
            ->pluck('branch_id', 'id')
            ->toArray();

        foreach ($propertyBranches as $propertyId => $branchId) {
            DB::table('tenancy_agreements')
                ->where('property_id', $propertyId)
                ->update(['branch_id' => $branchId]);

            DB::table('mous')
                ->where('property_id', $propertyId)
                ->update(['branch_id' => $branchId]);

            DB::table('maintenance_requests')
                ->where('property_id', $propertyId)
                ->update(['branch_id' => $branchId]);
        }

        // Backfill opportunities from mous
        $mouOpportunities = DB::table('mous')
            ->whereNotNull('opportunity_id')
            ->whereNotNull('branch_id')
            ->pluck('branch_id', 'opportunity_id')
            ->toArray();

        foreach ($mouOpportunities as $opportunityId => $branchId) {
            DB::table('opportunities')
                ->where('id', $opportunityId)
                ->update(['branch_id' => $branchId]);
        }

        DB::table('opportunities')->whereNull('branch_id')->update(['branch_id' => $ghyBranchId]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('maintenance_requests', 'branch_id')) {
            Schema::table('maintenance_requests', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasColumn('mous', 'branch_id')) {
            Schema::table('mous', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasColumn('tenancy_agreements', 'branch_id')) {
            Schema::table('tenancy_agreements', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasColumn('opportunities', 'branch_id')) {
            Schema::table('opportunities', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }

        if (Schema::hasColumn('properties', 'branch_id')) {
            Schema::table('properties', function (Blueprint $table) {
                $table->dropForeign(['branch_id']);
                $table->dropColumn('branch_id');
            });
        }
    }
};
