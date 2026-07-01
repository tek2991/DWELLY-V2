<x-filament-panels::page>
    {{-- Category Tab Bar --}}
    <div class="flex flex-wrap gap-1 border-b border-gray-200 dark:border-white/10 mb-6 -mt-2">
        @foreach ($this->getCategories() as $category)
            <button
                wire:click="$set('activeTab', '{{ $category->value }}')"
                @class([
                    'px-4 py-2.5 text-sm font-medium rounded-t-lg border-b-2 transition-colors duration-150 focus:outline-none',
                    'border-primary-500 text-primary-600 dark:text-primary-400 bg-primary-50 dark:bg-primary-950/30' => $activeTab === $category->value,
                    'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' => $activeTab !== $category->value,
                ])
            >
                <span class="flex items-center gap-1.5">
                    <x-filament::icon
                        :icon="$category->getIcon()"
                        class="w-4 h-4"
                    />
                    {{ $category->getPluralLabel() }}
                </span>
            </button>
        @endforeach
    </div>

    {{-- Reporting Classes & Accounts --}}
    <div class="space-y-4">
        @forelse ($this->accountsByReportingClass as $className => $accounts)
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between w-full">
                        <div>
                            <span class="font-semibold text-gray-900 dark:text-white">{{ $className }}</span>
                            <span class="ml-2 text-xs text-gray-500 dark:text-gray-400 font-normal">
                                {{ $accounts->count() }} {{ Str::plural('account', $accounts->count()) }}
                            </span>
                        </div>
                        {{ ($this->createAccountForClassAction)(['className' => $className]) }}
                    </div>
                </x-slot>

                @if ($accounts->isNotEmpty())
                    <div class="divide-y divide-gray-100 dark:divide-white/5 -mx-6 -mb-6">
                        @foreach ($accounts as $account)
                            <div @class([
                                'flex items-center justify-between px-6 py-3 group hover:bg-gray-50 dark:hover:bg-white/5 transition-colors',
                                'opacity-60' => $account->archived,
                                'pl-10' => $account->parent_id,
                                'pl-14' => $account->parent && $account->parent->parent_id,
                            ])>
                                {{-- Left: code + name --}}
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-mono font-medium bg-gray-100 dark:bg-white/10 text-gray-700 dark:text-gray-300 shrink-0">
                                        {{ $account->code }}
                                    </span>
                                    <span class="text-sm font-medium text-gray-900 dark:text-white truncate">
                                        {{ $account->name }}
                                    </span>
                                    @if ($account->archived)
                                        <x-filament::badge color="warning" size="sm">Archived</x-filament::badge>
                                    @endif
                                    @if ($account->default)
                                        <x-filament::badge color="primary" size="sm">Default</x-filament::badge>
                                    @endif
                                    @if ($account->is_control_account)
                                        <x-filament::badge color="gray" size="sm">Control</x-filament::badge>
                                    @endif
                                </div>

                                {{-- Right: currency + actions --}}
                                <div class="flex items-center gap-2 shrink-0 ml-4">
                                    <span class="text-xs text-gray-400 dark:text-gray-500 font-mono">
                                        {{ $account->currency_code }}
                                    </span>

                                    {{-- Edit button --}}
                                    <span class="opacity-0 group-hover:opacity-100 transition-opacity">
                                        {{ ($this->editAccountAction)(['account' => $account->id]) }}
                                    </span>

                                    {{-- Archive/Restore button --}}
                                    <span class="opacity-0 group-hover:opacity-100 transition-opacity">
                                        {{ ($this->toggleArchiveAction)(['account' => $account->id]) }}
                                    </span>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <p class="text-sm text-gray-400 dark:text-gray-500 italic py-2">
                        No accounts in this reporting class yet.
                    </p>
                @endif
            </x-filament::section>
        @empty
            <x-filament::section>
                <div class="flex flex-col items-center justify-center py-12 text-center">
                    <x-filament::icon icon="heroicon-o-clipboard-document-list" class="w-12 h-12 text-gray-300 dark:text-gray-600 mb-4" />
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white mb-1">No accounts yet</h3>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">
                        Get started by seeding the default chart of accounts or creating your first account.
                    </p>
                    {{ $this->createAccountAction }}
                </div>
            </x-filament::section>
        @endforelse
    </div>

    {{-- Filament action modals --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
