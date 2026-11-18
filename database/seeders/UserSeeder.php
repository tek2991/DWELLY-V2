<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Tek2991\Accounting\Models\Organization;
use Tek2991\Accounting\Models\State;

class UserSeeder extends Seeder
{
    /**
     * Seed application branches, users, roles, and branch assignments.
     */
    public function run(): void
    {
        $org = Organization::first();
        $assamState = State::where('name', 'Assam')->first();
        $karnatakaState = State::where('name', 'Karnataka')->first();

        // 1. Ensure core operational branches exist
        $ghyBranch = Branch::firstOrCreate(
            ['code' => 'GHY'],
            [
                'organization_id' => $org?->id ?? 1,
                'name' => 'Guwahati Operations Branch',
                'city' => 'Guwahati',
                'state_id' => $assamState?->id,
                'is_active' => true,
            ]
        );

        $blrBranch = Branch::firstOrCreate(
            ['code' => 'BLR'],
            [
                'organization_id' => $org?->id ?? 1,
                'name' => 'Bangalore Operations Branch',
                'city' => 'Bangalore',
                'state_id' => $karnatakaState?->id,
                'is_active' => true,
            ]
        );

        // 2. Ensure core users exist
        $adminUser = User::firstOrCreate(
            ['email' => 'admin@dwelly.in'],
            [
                'name' => 'Dwelly Admin',
                'password' => bcrypt('password'),
            ]
        );

        $executiveUser = User::firstOrCreate(
            ['email' => 'rahul.operations@dwelly.in'],
            [
                'name' => 'Rahul Goswami',
                'password' => bcrypt('password'),
            ]
        );

        $reviewerUser = User::firstOrCreate(
            ['email' => 'ananya.reviewer@dwelly.in'],
            [
                'name' => 'Ananya Sharma',
                'password' => bcrypt('password'),
            ]
        );

        // 3. Assign roles
        if (method_exists($adminUser, 'assignRole')) {
            $adminUser->syncRoles(['Business Owner']);
            $executiveUser->syncRoles(['Operations Executive']);
            $reviewerUser->syncRoles(['Operations Manager']);
        }

        // 4. Assign branches
        $allBranchIds = array_values(array_filter([$ghyBranch?->id, $blrBranch?->id]));

        if (!empty($allBranchIds)) {
            $adminUser->branches()->syncWithoutDetaching($allBranchIds);
        }

        if ($ghyBranch) {
            $executiveUser->branches()->syncWithoutDetaching([$ghyBranch->id]);
            $reviewerUser->branches()->syncWithoutDetaching([$ghyBranch->id]);
        }

        if ($blrBranch) {
            $reviewerUser->branches()->syncWithoutDetaching([$blrBranch->id]);
        }
    }
}
