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
        if ($user && method_exists($user, 'hasRole') && ($user->hasRole('admin') || $user->hasRole('Business Owner'))) {
            $this->branches = Branch::where('is_active', true)->get();
        } elseif ($user) {
            $this->branches = $user->branches()->where('is_active', true)->get();
        } else {
            $this->branches = collect();
        }
        
        $this->selectedBranchId = $branchContext->isAllBranches() ? 'all' : $branchContext->getCurrent()?->id;
    }

    public function updatedSelectedBranchId($value)
    {
        $branchContext = app(BranchContext::class);
        if ($value === 'all') {
            $branchContext->setAllBranches();
            $this->dispatch('branch-changed', branchId: 'all');
            return redirect(request()->header('Referer'));
        }
        
        $branch = Branch::find($value);
        if ($branch) {
            $branchContext->set($branch);
            $this->dispatch('branch-changed', branchId: $branch->id);
            return redirect(request()->header('Referer'));
        }
    }

    public function render()
    {
        return view('accounting::livewire.branch-selector');
    }
}
