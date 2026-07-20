<x-filament-panels::page>
    @php
        $totalItems = $record->items()->count();
        $approvedItems = $record->items()->where('status', \App\Domain\Audit\Enums\ItemStatus::APPROVED)->count();
        $rejectedItems = $record->items()->where('status', \App\Domain\Audit\Enums\ItemStatus::REJECTED)->count();
        $pendingItems = $totalItems - $approvedItems - $rejectedItems;
    @endphp
    
    <!-- Audit Summary -->
    <x-filament::section>
        <x-slot name="heading">
            <div style="display: flex; align-items: center; justify-content: space-between; width: 100%;">
                <span style="font-size: 1.125rem; font-weight: 600;">Audit Summary</span>
                <span style="font-size: 0.875rem; color: rgba(107, 114, 128, 1); background: rgba(243, 244, 246, 1); padding: 0.25rem 0.75rem; border-radius: 9999px;">
                    Review Round: <strong>{{ $record->review_round }}</strong>
                </span>
            </div>
        </x-slot>
        
        <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 1rem; margin-top: 1rem;">
            <div style="background: rgba(243, 244, 246, 1); padding: 1rem; border-radius: 0.5rem; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: rgba(17, 24, 39, 1);">{{ $totalItems }}</div>
                <div style="font-size: 0.875rem; font-weight: 500; color: rgba(107, 114, 128, 1); text-transform: uppercase;">Total Items</div>
            </div>
            
            <div style="background: rgba(220, 252, 231, 1); padding: 1rem; border-radius: 0.5rem; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: rgba(21, 128, 61, 1);">{{ $approvedItems }}</div>
                <div style="font-size: 0.875rem; font-weight: 500; color: rgba(22, 163, 74, 1); text-transform: uppercase;">Approved</div>
            </div>
            
            <div style="background: rgba(254, 242, 242, 1); padding: 1rem; border-radius: 0.5rem; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: rgba(220, 38, 38, 1);">{{ $rejectedItems }}</div>
                <div style="font-size: 0.875rem; font-weight: 500; color: rgba(239, 68, 68, 1); text-transform: uppercase;">Rejected</div>
            </div>
            
            <div style="background: rgba(254, 249, 195, 1); padding: 1rem; border-radius: 0.5rem; text-align: center;">
                <div style="font-size: 2rem; font-weight: 700; color: rgba(202, 138, 4, 1);">{{ $pendingItems }}</div>
                <div style="font-size: 0.875rem; font-weight: 500; color: rgba(234, 179, 8, 1); text-transform: uppercase;">Pending</div>
            </div>
        </div>
    </x-filament::section>

    <!-- Custom Livewire Component for Reviewing -->
    <livewire:operations.audit-review-component :audit="$record" />
</x-filament-panels::page>
