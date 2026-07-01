<div class="flex items-center gap-4 py-2 border-b border-gray-200 dark:border-white/10 last:border-0" style="padding-left: {{ $depth * 2 }}rem">
    <div class="flex-1">
        <div class="flex items-center gap-2">
            <span class="font-mono text-sm text-gray-500 dark:text-gray-400">{{ $account->code }}</span>
            <span class="font-medium text-gray-900 dark:text-white">{{ $account->name }}</span>
            @if($account->subtype)
                <x-filament::badge color="gray" size="sm">
                    {{ $account->subtype->name }}
                </x-filament::badge>
            @endif
            @if($account->default)
                <x-filament::badge color="success" size="sm">
                    Default
                </x-filament::badge>
            @endif
        </div>
        @if($account->description)
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $account->description }}</p>
        @endif
    </div>
    <div class="flex items-center gap-2">
        <span class="text-sm font-medium text-gray-900 dark:text-white">{{ $account->currency_code }}</span>
    </div>
</div>

@if($account->children->isNotEmpty())
    <div class="mt-2 space-y-2">
        @foreach($account->children as $child)
            @include('accounting::filament.pages.partials.account-tree-item', ['account' => $child, 'depth' => $depth + 1])
        @endforeach
    </div>
@endif
