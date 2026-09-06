<div class="px-4">
    <x-filament::input.wrapper>
        <x-filament::input.select wire:model.live="selectedBranchId">
            @if(auth()->user()?->hasRole('Business Owner') || auth()->user()?->hasRole('admin'))
                <option value="all">🌐 All Branches</option>
            @endif
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
