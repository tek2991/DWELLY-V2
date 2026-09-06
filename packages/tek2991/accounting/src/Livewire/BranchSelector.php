<?php

namespace Tek2991\Accounting\Livewire;

use Livewire\Component;
use App\Models\Branch;
use Tek2991\Accounting\Services\BranchContext;

class BranchSelector extends Component
{
    public $branches = [];
    public $selectedBranchId = null;

    public function mount(BranchContext $branchContext)
    {
        $user = auth()->user();
        $isOwner = $user && method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('Business Owner'));

        if ($isOwner) {
            $this->branches = Branch::where('is_active', true)->get();
            $this->selectedBranchId = $branchContext->isAllBranches() ? 'all' : ($branchContext->getCurrent()?->id ?? 'all');
        } elseif ($user) {
            $this->branches = $user->branches()->where('is_active', true)->get();
            $currentId = $branchContext->getCurrent()?->id;
            $userBranchIds = $this->branches->pluck('id')->toArray();

            if ($currentId && in_array($currentId, $userBranchIds)) {
                $this->selectedBranchId = $currentId;
            } else {
                $first = $this->branches->first();
                if ($first) {
                    $this->selectedBranchId = $first->id;
                    $branchContext->set($first);
                }
            }
        } else {
            $this->branches = collect();
        }
    }

    public function updatedSelectedBranchId($value)
    {
        $branchContext = app(BranchContext::class);
        $user = auth()->user();
        $isOwner = $user && method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('Business Owner'));

        if ($value === 'all') {
            if ($isOwner) {
                $branchContext->setAllBranches();
                $this->dispatch('branch-changed', branchId: 'all');
                return redirect(request()->header('Referer') ?? '/');
            }
            return;
        }

        $branch = Branch::find($value);
        if ($branch) {
            if ($isOwner || ($user && $user->branches()->where('branches.id', $branch->id)->exists())) {
                $branchContext->set($branch);
                $this->dispatch('branch-changed', branchId: $branch->id);
                return redirect(request()->header('Referer') ?? '/');
            }
        }
    }

    public function render()
    {
        return view('accounting::livewire.branch-selector');
    }
}
