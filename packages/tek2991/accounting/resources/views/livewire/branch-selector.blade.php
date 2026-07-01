<div class="px-4">
    <x-filament::input.wrapper>
        <x-filament::input.select wire:model.live="selectedBranchId">
            <option value="">Select Branch...</option>
            @foreach($branches as $branch)
                <option value="{{ $branch->id }}">{{ $branch->name }}</option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</div>
