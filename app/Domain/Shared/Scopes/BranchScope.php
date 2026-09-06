<?php

namespace App\Domain\Shared\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Tek2991\Accounting\Services\BranchContext;

class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // 1. If not running in an authenticated user session (CLI commands, seeders, queues), do not restrict
        if (! auth()->check()) {
            return;
        }

        $user = auth()->user();
        $table = $model->getTable();
        $column = "{$table}.branch_id";

        $branchContext = app(BranchContext::class);
        $currentBranchId = $branchContext->getCurrentId();
        $isAllBranches = $branchContext->isAllBranches();

        // 2. Business Owner (or admin role)
        $isOwner = method_exists($user, 'hasRole') && ($user->hasRole('Business Owner') || $user->hasRole('admin'));

        if ($isOwner) {
            // If the owner explicitly chose a specific branch in the switcher, scope to that branch
            if ($currentBranchId && ! $isAllBranches) {
                $builder->where($column, $currentBranchId);
            }
            // When "All Branches" is active or no branch is selected, Owner sees everything across all branches
            return;
        }

        // 3. Regular Branch Staff (Operations Executive, Operations Manager, Accountant, etc.)
        $userBranchIds = method_exists($user, 'branches')
            ? $user->branches()->pluck('branches.id')->toArray()
            : [];

        // If staff user is not assigned to any branch
        if (empty($userBranchIds)) {
            // If the user has a restricted staff role, they must not access resources without an assigned branch
            $isRestrictedStaff = method_exists($user, 'hasAnyRole') && $user->hasAnyRole([
                'Operations Executive',
                'Operations Manager',
                'Accountant',
            ]);

            if ($isRestrictedStaff) {
                $builder->whereRaw('1 = 0');
                return;
            }

            // Unassigned users (e.g. general test runners without branch configuration)
            if ($currentBranchId && ! $isAllBranches) {
                $builder->where($column, $currentBranchId);
            }
            return;
        }

        // If user selected one of their assigned branches, scope to that branch
        if ($currentBranchId && in_array($currentBranchId, $userBranchIds, true)) {
            $builder->where($column, $currentBranchId);
        } else {
            // Default to all their authorized branches
            $builder->whereIn($column, $userBranchIds);
        }
    }
}
