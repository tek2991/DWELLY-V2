<?php

namespace Tek2991\Accounting\Services;

use App\Models\Branch;
use Tek2991\Accounting\Models\Organization;

class BranchContext
{
    protected ?Branch $branch = null;
    protected bool $allBranches = false;

    public function getCurrent(): ?Branch
    {
        if ($this->allBranches) {
            return null;
        }

        if ($this->branch) {
            return $this->branch;
        }

        $sessionKey = config('accounting.branch_session_key', 'accounting_branch_id');
        $branchId = session($sessionKey);

        if ($branchId === 'all') {
            $this->allBranches = true;
            return null;
        }

        if ($branchId) {
            $this->branch = Branch::find($branchId);
        }

        return $this->branch;
    }

    public function getCurrentId(): ?int
    {
        return $this->getCurrent()?->id;
    }

    public function isAllBranches(): bool
    {
        if ($this->allBranches) {
            return true;
        }

        $sessionKey = config('accounting.branch_session_key', 'accounting_branch_id');
        return session($sessionKey) === 'all';
    }

    public function set(Branch $branch): void
    {
        $this->branch = $branch;
        $this->allBranches = false;
        
        $sessionKey = config('accounting.branch_session_key', 'accounting_branch_id');
        session([$sessionKey => $branch->id]);
    }

    public function setAllBranches(): void
    {
        $this->branch = null;
        $this->allBranches = true;
        
        $sessionKey = config('accounting.branch_session_key', 'accounting_branch_id');
        session([$sessionKey => 'all']);
    }

    public function clear(): void
    {
        $this->branch = null;
        $this->allBranches = false;
        
        $sessionKey = config('accounting.branch_session_key', 'accounting_branch_id');
        session()->forget($sessionKey);
    }

    public function getOrganization(): Organization
    {
        return Organization::current();
    }

    public function applyQueryScope($query)
    {
        $branchId = $this->getCurrent()?->id;
        if ($branchId) {
            $query->where('branch_id', $branchId);
        } else {
            $user = auth()->user();
            if ($user && method_exists($user, 'hasRole') && !$user->hasRole('admin')) {
                $query->whereIn('branch_id', $user->branches->pluck('id'));
            }
        }
        return $query;
    }
}
